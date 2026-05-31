<?php

declare(strict_types=1);

namespace Sift\Tools\Pint;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use Sift\Tools\Testing\ReportPathNormalizer;
use UnexpectedValueException;

final readonly class PintParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): PintReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $result = $this->stringValue($document, 'result');
        $items = $this->fileItems($this->list($document['files'] ?? [], 'files'), $cwd);
        $fixers = array_sum(array_map(
            static fn(array $item): int => is_array($item['fixers'] ?? null) ? count($item['fixers']) : 0,
            $items,
        ));

        return new PintReport(
            summary: [
                'result' => $result,
                'files' => count($items),
                'fixers' => $fixers,
            ],
            items: $items,
            result: $result,
            files: count($items),
            fixers: $fixers,
        );
    }

    /**
     * @param list<mixed> $files
     * @return list<array<string, mixed>>
     */
    private function fileItems(array $files, string $cwd): array
    {
        $items = [];

        foreach ($files as $file) {
            $file = $this->object($file, 'files[]');
            $items[] = [
                'type' => ItemType::ChangedFile->value,
                'file' => $this->pathNormalizer->normalize($this->stringValue($file, 'path'), $cwd),
                'fixers' => $this->stringList($file['fixers'] ?? [], 'fixers'),
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
            throw $this->invalidShape(sprintf('Pint "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('Pint "%s" must use string keys.', $field));
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
            throw $this->invalidShape(sprintf('Pint "%s" must be a list.', $field));
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
                throw $this->invalidShape(sprintf('Pint "%s" must contain only strings.', $field));
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
            throw $this->invalidShape(sprintf('Pint "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse Pint JSON output.',
            context: ['reason' => $message],
        );
    }
}
