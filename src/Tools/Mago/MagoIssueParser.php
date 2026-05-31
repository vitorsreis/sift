<?php

declare(strict_types=1);

namespace Sift\Tools\Mago;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use Sift\Tools\Testing\ReportPathNormalizer;
use UnexpectedValueException;

final readonly class MagoIssueParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(
        string $stdout,
        string $stderr,
        string $cwd,
        ?ItemType $forcedItemType = null,
    ): MagoReport {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $issues = $this->list($document['issues'] ?? [], 'issues');
        $items = [];
        $levels = [
            'errors' => 0,
            'warnings' => 0,
            'notes' => 0,
            'help' => 0,
        ];
        $fixable = 0;
        $files = [];

        foreach ($issues as $issue) {
            $issue = $this->object($issue, 'issues[]');
            $level = $this->stringValue($issue, 'level');
            $item = [
                'type' => ($forcedItemType ?? $this->itemType($level))->value,
                'message' => $this->stringValue($issue, 'message'),
                'severity' => $level,
            ];

            $code = $issue['code'] ?? null;
            if (is_string($code) && $code !== '') {
                $item['rule'] = $code;
            }

            $annotation = $this->primaryAnnotation($this->list($issue['annotations'] ?? [], 'annotations'));
            if ($annotation !== null) {
                $this->applyAnnotation($item, $annotation, $cwd);

                if (isset($item['file']) && is_string($item['file'])) {
                    $files[$item['file']] = true;
                }
            }

            $notes = $this->stringList($issue['notes'] ?? [], 'notes');
            if ($notes !== []) {
                $item['notes'] = $notes;
            }

            foreach (['help', 'link'] as $field) {
                $value = $issue[$field] ?? null;

                if (is_string($value) && $value !== '') {
                    $item[$field] = $value;
                }
            }

            $edits = $issue['edits'] ?? [];
            if (is_array($edits) && $edits !== []) {
                $item['fixable'] = true;
                ++$fixable;
            }

            $this->incrementLevel($levels, $level);
            $items[] = $item;
        }

        return new MagoReport(
            summary: [
                'issues' => count($items),
                'errors' => $levels['errors'],
                'warnings' => $levels['warnings'],
                'notes' => $levels['notes'],
                'help' => $levels['help'],
                'fixable' => $fixable,
                'files' => count($files),
            ],
            items: $items,
            findings: count($items),
        );
    }

    private function itemType(string $level): ItemType
    {
        return match ($level) {
            'error' => ItemType::Error,
            'warning' => ItemType::Warning,
            default => ItemType::Issue,
        };
    }

    /**
     * @param array{errors: int, warnings: int, notes: int, help: int} $levels
     */
    private function incrementLevel(array &$levels, string $level): void
    {
        match ($level) {
            'error' => ++$levels['errors'],
            'warning' => ++$levels['warnings'],
            'note' => ++$levels['notes'],
            'help' => ++$levels['help'],
            default => null,
        };
    }

    /**
     * @param list<mixed> $annotations
     * @return array<string, mixed>|null
     */
    private function primaryAnnotation(array $annotations): ?array
    {
        if ($annotations === []) {
            return null;
        }

        foreach ($annotations as $annotation) {
            $annotation = $this->object($annotation, 'annotations[]');

            if (($annotation['kind'] ?? null) === 'primary') {
                return $annotation;
            }
        }

        return $this->object($annotations[0], 'annotations[]');
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $annotation
     */
    private function applyAnnotation(array &$item, array $annotation, string $cwd): void
    {
        $span = $this->object($annotation['span'] ?? [], 'annotation.span');
        $fileId = $this->object($span['file_id'] ?? [], 'annotation.span.file_id');
        $file = $fileId['path'] ?? $fileId['name'] ?? null;

        if (is_string($file) && $file !== '') {
            $item['file'] = $this->pathNormalizer->normalize($file, $cwd);
        }

        $start = $this->object($span['start'] ?? [], 'annotation.span.start');

        foreach (['line', 'column'] as $field) {
            $value = $start[$field] ?? null;

            if (is_int($value)) {
                $item[$field] = $value;
            }
        }

        foreach (['kind' => 'issue_type', 'message' => 'annotation'] as $source => $target) {
            $value = $annotation[$source] ?? null;

            if (is_string($value) && $value !== '') {
                $item[$target] = $value;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw $this->invalidShape(sprintf('Mago "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('Mago "%s" must use string keys.', $field));
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
            throw $this->invalidShape(sprintf('Mago "%s" must be a list.', $field));
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
                throw $this->invalidShape(sprintf('Mago "%s" must contain only strings.', $field));
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
            throw $this->invalidShape(sprintf('Mago "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse Mago JSON output.',
            context: ['reason' => $message],
        );
    }
}
