<?php

declare(strict_types=1);

namespace Sift\Tools\EasyCodingStandard;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use Sift\Tools\Testing\ReportPathNormalizer;
use UnexpectedValueException;

final readonly class EasyCodingStandardParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): EasyCodingStandardReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $totals = $this->object($document['totals'] ?? [], 'totals');
        $errors = $this->intValue($totals, 'errors');
        $diffs = $this->intValue($totals, 'diffs');
        $files = $this->files($document['files'] ?? []);
        $items = [];

        foreach ($files as $path => $result) {
            $result = $this->object($result, sprintf('files.%s', $path));
            $normalizedPath = $this->pathNormalizer->normalize($path, $cwd);

            foreach ($this->list($result['errors'] ?? [], 'errors') as $error) {
                $error = $this->object($error, 'errors[]');
                $filePath = $error['file_path'] ?? $normalizedPath;

                if (! is_string($filePath) || $filePath === '') {
                    throw $this->invalidShape('ECS "file_path" must be a non-empty string.');
                }

                $items[] = [
                    'type' => ItemType::Issue->value,
                    'file' => $this->pathNormalizer->normalize($filePath, $cwd),
                    'line' => $this->intValue($error, 'line'),
                    'message' => $this->stringValue($error, 'message'),
                    'rule' => $this->stringValue($error, 'source_class'),
                ];
            }

            foreach ($this->list($result['diffs'] ?? [], 'diffs') as $diff) {
                $diff = $this->object($diff, 'diffs[]');
                $items[] = [
                    'type' => ItemType::Diff->value,
                    'file' => $normalizedPath,
                    'diff' => $this->stringValue($diff, 'diff'),
                    'applied_checkers' => $this->stringList($diff['applied_checkers'] ?? [], 'applied_checkers'),
                ];
            }
        }

        return new EasyCodingStandardReport(
            summary: ['errors' => $errors, 'diffs' => $diffs, 'files' => count($files)],
            items: $items,
            errors: $errors,
            diffs: $diffs,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function files(mixed $value): array
    {
        if ($value === []) {
            return [];
        }

        return $this->object($value, 'files');
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw $this->invalidShape(sprintf('ECS "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('ECS "%s" must use string keys.', $field));
            }

            $object[$key] = $item;
        }

        return $object;
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->invalidShape(sprintf('ECS "%s" must be a list.', $field));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, string $field): array
    {
        $values = $this->list($value, $field);
        $strings = [];

        foreach ($values as $item) {
            if (! is_string($item)) {
                throw $this->invalidShape(sprintf('ECS "%s" must contain only strings.', $field));
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function intValue(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        if (! is_int($value)) {
            throw $this->invalidShape(sprintf('ECS "%s" must be an integer.', $key));
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
            throw $this->invalidShape(sprintf('ECS "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse Easy Coding Standard JSON output.',
            hint: 'Use --debug to inspect limited raw output snippets.',
            context: ['reason' => $message],
        );
    }
}
