<?php

declare(strict_types=1);

namespace Sift\Tools\PhpCs;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use Sift\Tools\Testing\ReportPathNormalizer;
use UnexpectedValueException;

final readonly class PhpcsParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): PhpcsReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $totals = $this->object($document['totals'] ?? [], 'totals');
        $errors = $this->intValue($totals, 'errors');
        $warnings = $this->intValue($totals, 'warnings');
        $fixable = $this->intValue($totals, 'fixable');
        $files = $this->objectOrEmptyList($document['files'] ?? [], 'files');
        $items = $this->fileItems($files, $cwd);

        return new PhpcsReport(
            summary: [
                'errors' => $errors,
                'warnings' => $warnings,
                'fixable' => $fixable,
                'files' => count($files),
                'messages' => count($items),
            ],
            items: $items,
            findings: $errors + $warnings,
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
                    'type' => $this->itemType($this->stringValue($messageReport, 'type')),
                    'file' => $this->pathNormalizer->normalize($file, $cwd),
                ];

                $line = $messageReport['line'] ?? null;

                if (is_int($line)) {
                    $item['line'] = $line;
                }

                $column = $messageReport['column'] ?? null;

                if (is_int($column)) {
                    $item['column'] = $column;
                }

                $item['message'] = $this->stringValue($messageReport, 'message');
                $item['rule'] = $this->stringValue($messageReport, 'source');

                $severity = $messageReport['severity'] ?? null;

                if (is_int($severity)) {
                    $item['severity'] = $severity;
                }

                $fixable = $messageReport['fixable'] ?? null;

                if (is_bool($fixable)) {
                    $item['fixable'] = $fixable;
                }

                $items[] = $item;
            }
        }

        return $items;
    }

    private function itemType(string $type): string
    {
        return strtoupper($type) === 'WARNING'
            ? ItemType::Warning->value
            : ItemType::Issue->value;
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw $this->invalidShape(sprintf('PHPCS "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('PHPCS "%s" must use string keys.', $field));
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
            throw $this->invalidShape(sprintf('PHPCS "%s" must be a list.', $field));
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
            throw $this->invalidShape(sprintf('PHPCS "%s" must be an integer.', $key));
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
            throw $this->invalidShape(sprintf('PHPCS "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse PHPCS JSON output.',
            context: ['reason' => $message],
        );
    }
}
