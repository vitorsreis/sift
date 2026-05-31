<?php

declare(strict_types=1);

namespace Sift\Tools\PhpCsFixer;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use Sift\Tools\Testing\ReportPathNormalizer;
use UnexpectedValueException;

final readonly class PhpCsFixerParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): PhpCsFixerReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $files = $this->list($document['files'] ?? [], 'files');
        $items = $this->items($files, $cwd);
        $fixers = array_sum(array_map(
            static fn(array $item): int => is_array($item['applied_fixers'] ?? null) ? count($item['applied_fixers']) : 0,
            array_filter($items, static fn(array $item): bool => $item['type'] === ItemType::ChangedFile->value),
        ));
        $diffs = count(array_filter($items, static fn(array $item): bool => $item['type'] === ItemType::Diff->value));

        return new PhpCsFixerReport(
            summary: [
                'files' => count($files),
                'fixers' => $fixers,
                'diffs' => $diffs,
                'time_total' => $this->optionalFloat($this->object($document['time'] ?? [], 'time'), 'total'),
                'memory' => $this->optionalFloat($document, 'memory'),
            ],
            items: $items,
            files: count($files),
            fixers: $fixers,
            diffs: $diffs,
        );
    }

    /**
     * @param list<mixed> $files
     *
     * @return list<array<string, mixed>>
     */
    private function items(array $files, string $cwd): array
    {
        $items = [];

        foreach ($files as $file) {
            $file = $this->object($file, 'files[]');
            $path = $this->pathNormalizer->normalize($this->stringValue($file, 'name'), $cwd);
            $appliedFixers = $this->stringList($file['appliedFixers'] ?? [], 'appliedFixers');

            $items[] = [
                'type' => ItemType::ChangedFile->value,
                'file' => $path,
                'applied_fixers' => $appliedFixers,
            ];

            $diff = $file['diff'] ?? null;

            if (is_string($diff) && $diff !== '') {
                $items[] = [
                    'type' => ItemType::Diff->value,
                    'file' => $path,
                    'diff' => $diff,
                    'applied_fixers' => $appliedFixers,
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw $this->invalidShape(sprintf('PHP CS Fixer "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('PHP CS Fixer "%s" must use string keys.', $field));
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
            throw $this->invalidShape(sprintf('PHP CS Fixer "%s" must be a list.', $field));
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
                throw $this->invalidShape(sprintf('PHP CS Fixer "%s" must contain only strings.', $field));
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw $this->invalidShape(sprintf('PHP CS Fixer "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function optionalFloat(array $values, string $key): ?float
    {
        $value = $values[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value) && ! is_float($value)) {
            throw $this->invalidShape(sprintf('PHP CS Fixer "%s" must be numeric.', $key));
        }

        return (float) $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse PHP CS Fixer JSON output.',
            hint: 'Use --debug to inspect limited raw output snippets.',
            context: ['reason' => $message],
        );
    }
}
