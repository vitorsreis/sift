<?php

declare(strict_types=1);

namespace Sift\Tools\Rector;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use Sift\Tools\Testing\ReportPathNormalizer;
use UnexpectedValueException;

final readonly class RectorParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): RectorReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $totals = $this->object($document['totals'] ?? [], 'totals');
        $changedFiles = $this->intValue($totals, 'changed_files');
        $errors = $this->intValue($totals, 'errors');
        $changedFileItems = $this->changedFileItems($this->list($document['changed_files'] ?? [], 'changed_files'), $cwd);
        $diffItems = $this->diffItems($this->list($document['file_diffs'] ?? [], 'file_diffs'), $cwd);
        $errorItems = $this->errorItems($this->list($document['errors'] ?? [], 'errors'), $cwd);

        return new RectorReport(
            summary: [
                'changed_files' => $changedFiles,
                'errors' => $errors,
                'diffs' => count($diffItems),
            ],
            items: [...$changedFileItems, ...$diffItems, ...$errorItems],
            changedFiles: $changedFiles,
            errors: $errors,
        );
    }

    /**
     * @param list<mixed> $changedFiles
     * @return list<array<string, mixed>>
     */
    private function changedFileItems(array $changedFiles, string $cwd): array
    {
        $items = [];

        foreach ($changedFiles as $file) {
            if (! is_string($file) || $file === '') {
                throw $this->invalidShape('Rector changed files must be strings.');
            }

            $items[] = [
                'type' => ItemType::ChangedFile->value,
                'file' => $this->pathNormalizer->normalize($file, $cwd),
            ];
        }

        return $items;
    }

    /**
     * @param list<mixed> $diffs
     * @return list<array<string, mixed>>
     */
    private function diffItems(array $diffs, string $cwd): array
    {
        $items = [];

        foreach ($diffs as $diff) {
            $diff = $this->object($diff, 'file_diffs[]');
            $items[] = [
                'type' => ItemType::Diff->value,
                'file' => $this->pathNormalizer->normalize($this->stringValue($diff, 'file'), $cwd),
                'diff' => $this->stringValue($diff, 'diff'),
                'applied_rectors' => $this->stringList($diff['applied_rectors'] ?? [], 'applied_rectors'),
            ];
        }

        return $items;
    }

    /**
     * @param list<mixed> $errors
     * @return list<array<string, mixed>>
     */
    private function errorItems(array $errors, string $cwd): array
    {
        $items = [];

        foreach ($errors as $error) {
            $error = $this->object($error, 'errors[]');
            $item = [
                'type' => ItemType::Error->value,
                'file' => $this->pathNormalizer->normalize($this->stringValue($error, 'file'), $cwd),
            ];

            $line = $error['line'] ?? null;

            if (is_int($line)) {
                $item['line'] = $line;
            }

            $item['message'] = $this->stringValue($error, 'message');

            $causedBy = $error['caused_by'] ?? null;

            if (is_string($causedBy) && $causedBy !== '') {
                $item['caused_by'] = $causedBy;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw $this->invalidShape(sprintf('Rector "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('Rector "%s" must use string keys.', $field));
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
            throw $this->invalidShape(sprintf('Rector "%s" must be a list.', $field));
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
                throw $this->invalidShape(sprintf('Rector "%s" must contain only strings.', $field));
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
        $value = $values[$key] ?? 0;

        if (! is_int($value)) {
            throw $this->invalidShape(sprintf('Rector "%s" must be an integer.', $key));
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
            throw $this->invalidShape(sprintf('Rector "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse Rector JSON output.',
            context: ['reason' => $message],
        );
    }
}
