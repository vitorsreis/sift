<?php

declare(strict_types=1);

namespace Sift\Tools\Psalm;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use Sift\Tools\Testing\ReportPathNormalizer;

final readonly class PsalmParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): PsalmReport
    {
        $issues = $this->issues($this->jsonOutputParser->parse($stdout, $stderr)->decoded());
        $items = [];
        $errors = 0;
        $warnings = 0;
        $info = 0;

        foreach ($issues as $issue) {
            $issue = $this->object($issue, 'issue');
            $severity = $this->stringValue($issue, 'severity');

            if ($severity === 'error') {
                ++$errors;
            } elseif ($severity === 'warning') {
                ++$warnings;
            } else {
                ++$info;
            }

            $item = [
                'type' => ItemType::Issue->value,
                'severity' => $severity,
                'issue_type' => $this->stringValue($issue, 'type'),
                'file' => $this->pathNormalizer->normalize($this->file($issue), $cwd),
            ];

            $line = $issue['line_from'] ?? null;

            if (is_int($line)) {
                $item['line'] = $line;
            }

            $column = $issue['column_from'] ?? null;

            if (is_int($column)) {
                $item['column'] = $column;
            }

            $item['message'] = $this->stringValue($issue, 'message');
            $items[] = $item;
        }

        return new PsalmReport(
            summary: [
                'issues' => count($items),
                'errors' => $errors,
                'warnings' => $warnings,
                'info' => $info,
            ],
            items: $items,
            findings: count($items),
        );
    }

    /**
     * @return list<mixed>
     */
    private function issues(mixed $decoded): array
    {
        if (is_array($decoded) && array_is_list($decoded)) {
            return $decoded;
        }

        $object = $this->object($decoded, 'root');
        $issues = $object['issues'] ?? null;

        if (is_array($issues) && array_is_list($issues)) {
            return $issues;
        }

        throw $this->invalidShape('Psalm JSON output must be a list of issues.');
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw $this->invalidShape(sprintf('Psalm "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('Psalm "%s" must use string keys.', $field));
            }

            $object[$key] = $item;
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $issue
     */
    private function file(array $issue): string
    {
        $file = $issue['file_path'] ?? $issue['file_name'] ?? null;

        if (! is_string($file) || $file === '') {
            throw $this->invalidShape('Psalm issue file must be a non-empty string.');
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw $this->invalidShape(sprintf('Psalm "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse Psalm JSON output.',
            context: ['reason' => $message],
        );
    }
}
