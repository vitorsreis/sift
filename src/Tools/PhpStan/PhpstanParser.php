<?php

declare(strict_types=1);

namespace Sift\Tools\PhpStan;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use Sift\Tools\Testing\ReportPathNormalizer;
use UnexpectedValueException;

final readonly class PhpstanParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): PhpstanReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $totals = $this->object($document['totals'] ?? [], 'totals');
        $errors = $this->intValue($totals, 'errors');
        $fileErrors = $this->intValue($totals, 'file_errors');
        $files = $this->objectOrEmptyList($document['files'] ?? [], 'files');
        $items = $this->fileItems($files, $cwd);
        $items = [...$items, ...$this->errorItems($this->list($document['errors'] ?? [], 'errors'))];

        return new PhpstanReport(
            summary: [
                'errors' => $errors,
                'file_errors' => $fileErrors,
                'files' => count($files),
                'messages' => count(array_filter(
                    $items,
                    static fn(array $item): bool => ($item['type'] ?? null) === ItemType::Issue->value,
                )),
            ],
            items: $items,
            findings: $errors + $fileErrors,
        );
    }

    /**
     * @param array<string, mixed> $files
     * @return list<array<string, mixed>>
     */
    private function fileItems(array $files, string $cwd): array
    {
        $items = [];

        foreach ($files as $file => $report) {
            $fileReport = $this->object($report, sprintf('files.%s', $file));
            $messages = $this->list($fileReport['messages'] ?? [], sprintf('files.%s.messages', $file));

            foreach ($messages as $message) {
                $messageReport = $this->object($message, sprintf('files.%s.messages[]', $file));
                $item = [
                    'type' => ItemType::Issue->value,
                    'file' => $this->pathNormalizer->normalize($file, $cwd),
                ];

                $line = $messageReport['line'] ?? null;

                if (is_int($line)) {
                    $item['line'] = $line;
                }

                $item['message'] = $this->stringValue($messageReport, 'message');

                $identifier = $messageReport['identifier'] ?? null;

                if (is_string($identifier) && $identifier !== '') {
                    $item['identifier'] = $identifier;
                }

                $ignorable = $messageReport['ignorable'] ?? null;

                if (is_bool($ignorable)) {
                    $item['ignorable'] = $ignorable;
                }

                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param list<mixed> $errors
     * @return list<array<string, mixed>>
     */
    private function errorItems(array $errors): array
    {
        $items = [];

        foreach ($errors as $error) {
            if (! is_string($error)) {
                throw $this->invalidShape('PHPStan errors must be strings.');
            }

            $items[] = [
                'type' => ItemType::Error->value,
                'message' => $error,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw $this->invalidShape(sprintf('PHPStan "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('PHPStan "%s" must use string keys.', $field));
            }

            $object[$key] = $item;
        }

        return $object;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectOrEmptyList(mixed $value, string $field): array
    {
        if (is_array($value) && array_is_list($value) && $value === []) {
            return [];
        }

        return $this->object($value, $field);
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->invalidShape(sprintf('PHPStan "%s" must be a list.', $field));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function intValue(array $values, string $key): int
    {
        $value = $values[$key] ?? 0;

        if (! is_int($value)) {
            throw $this->invalidShape(sprintf('PHPStan "%s" must be an integer.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw $this->invalidShape(sprintf('PHPStan "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse PHPStan JSON output.',
            context: ['reason' => $message],
        );
    }
}
