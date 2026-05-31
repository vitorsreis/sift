<?php

declare(strict_types=1);

namespace Sift\Tools\Deptrac;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use Sift\Tools\Testing\ReportPathNormalizer;
use UnexpectedValueException;

final readonly class DeptracParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): DeptracReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $report = $this->object($document['Report'] ?? null, 'Report');
        $files = $this->files($document['files'] ?? []);
        $violations = $this->intValue($report, 'Violations');
        $errors = $this->intValue($report, 'Errors');

        return new DeptracReport(
            summary: [
                'violations' => $violations,
                'skipped_violations' => $this->intValue($report, 'Skipped violations'),
                'uncovered' => $this->intValue($report, 'Uncovered'),
                'allowed' => $this->intValue($report, 'Allowed'),
                'warnings' => $this->intValue($report, 'Warnings'),
                'errors' => $errors,
                'files' => count($files),
            ],
            items: $this->items($files, $cwd),
            violations: $violations,
            errors: $errors,
        );
    }

    /**
     * @param array<string, mixed> $files
     *
     * @return list<array<string, mixed>>
     */
    private function items(array $files, string $cwd): array
    {
        $items = [];

        foreach ($files as $file => $payload) {
            if ($file === '') {
                throw $this->invalidShape('Deptrac files must use non-empty string keys.');
            }

            $filePayload = $this->object($payload, 'files[]');

            foreach ($this->list($filePayload['messages'] ?? [], 'messages') as $message) {
                $items[] = $this->messageItem(
                    file: $this->pathNormalizer->normalize($file, $cwd),
                    message: $this->object($message, 'messages[]'),
                );
            }
        }

        return $items;
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
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    private function messageItem(string $file, array $message): array
    {
        $type = $this->stringValue($message, 'type');
        $text = $this->stringValue($message, 'message');
        $item = [
            'type' => $type === 'error' ? ItemType::ArchitectureViolation->value : ItemType::Warning->value,
            'file' => $file,
            'line' => $this->intValue($message, 'line'),
            'message' => $text,
            'rule' => $this->rule($type, $text),
        ];

        return [...$item, ...$this->messageDetails($text)];
    }

    /**
     * @return array<string, string>
     */
    private function messageDetails(string $message): array
    {
        if (preg_match('/^(?<depender>.+) must not depend on (?<dependency>.+) \((?<depender_layer>.+) on (?<dependent_layer>.+)\)$/', $message, $matches) === 1) {
            return $this->dependencyDetails($matches);
        }

        if (preg_match('/^(?<depender>.+) should not depend on (?<dependency>.+) \((?<depender_layer>.+) on (?<dependent_layer>.+)\)$/', $message, $matches) === 1) {
            return $this->dependencyDetails($matches);
        }

        if (preg_match('/^(?<depender>.+) has uncovered dependency on (?<dependency>.+) \((?<layer>.+)\)$/', $message, $matches) === 1) {
            return [
                'layer' => trim($matches['layer']),
                'depender' => trim($matches['depender']),
                'dependency' => trim($matches['dependency']),
            ];
        }

        return [];
    }

    /**
     * @param array<int|string, string> $matches
     *
     * @return array<string, string>
     */
    private function dependencyDetails(array $matches): array
    {
        $dependerLayer = trim($matches['depender_layer']);

        return [
            'layer' => $dependerLayer,
            'depender' => trim($matches['depender']),
            'dependency' => trim($matches['dependency']),
            'depender_layer' => $dependerLayer,
            'dependent_layer' => trim($matches['dependent_layer']),
        ];
    }

    private function rule(string $type, string $message): string
    {
        if (str_contains($message, ' has uncovered dependency on ')) {
            return 'uncovered_dependency';
        }

        if ($type === 'warning') {
            return 'skipped_violation';
        }

        return 'forbidden_dependency';
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw $this->invalidShape(sprintf('Deptrac "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('Deptrac "%s" must use string keys.', $field));
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
            throw $this->invalidShape(sprintf('Deptrac "%s" must be a list.', $field));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function intValue(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        if (! is_int($value)) {
            throw $this->invalidShape(sprintf('Deptrac "%s" must be an integer.', $key));
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
            throw $this->invalidShape(sprintf('Deptrac "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse Deptrac JSON output.',
            hint: 'Use --debug to inspect limited raw output snippets.',
            context: ['reason' => $message],
        );
    }
}
