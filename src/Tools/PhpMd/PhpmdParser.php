<?php

declare(strict_types=1);

namespace Sift\Tools\PhpMd;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use Sift\Tools\Testing\ReportPathNormalizer;
use UnexpectedValueException;

final readonly class PhpmdParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): PhpmdReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $files = $this->list($document['files'] ?? [], 'files');
        $items = $this->items($files, $cwd);
        $rules = [];

        foreach ($items as $item) {
            $rule = $item['rule'] ?? null;

            if (is_string($rule)) {
                $rules[$rule] = true;
            }
        }

        $priorities = array_filter(array_map(
            static fn(array $item): mixed => $item['priority'] ?? null,
            $items,
        ), is_int(...));

        return new PhpmdReport(
            summary: [
                'files' => count($files),
                'violations' => count($items),
                'rules' => count($rules),
                'highest_priority' => $priorities === [] ? null : min($priorities),
            ],
            items: $items,
            violations: count($items),
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
            $fileReport = $this->object($file, 'files[]');
            $path = $this->pathNormalizer->normalize($this->stringValue($fileReport, 'file'), $cwd);
            $violations = $this->list($fileReport['violations'] ?? [], 'files[].violations');

            foreach ($violations as $violation) {
                $violationReport = $this->object($violation, 'files[].violations[]');
                $item = [
                    'type' => ItemType::Issue->value,
                    'file' => $path,
                    'line' => $this->intValue($violationReport, 'beginLine'),
                    'end_line' => $this->intValue($violationReport, 'endLine'),
                    'message' => $this->stringValue($violationReport, 'description'),
                    'rule' => $this->stringValue($violationReport, 'rule'),
                    'ruleset' => $this->stringValue($violationReport, 'ruleSet'),
                    'priority' => $this->intValue($violationReport, 'priority'),
                ];

                $externalInfoUrl = $violationReport['externalInfoUrl'] ?? null;

                if (is_string($externalInfoUrl) && $externalInfoUrl !== '') {
                    $item['external_info_url'] = $externalInfoUrl;
                }

                foreach (['package', 'class', 'method', 'function'] as $optionalStringField) {
                    $value = $violationReport[$optionalStringField] ?? null;

                    if (is_string($value) && $value !== '') {
                        $item[$optionalStringField] = $value;
                    }
                }

                $items[] = $item;
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
            throw $this->invalidShape(sprintf('PHPMD "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('PHPMD "%s" must use string keys.', $field));
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
            throw $this->invalidShape(sprintf('PHPMD "%s" must be a list.', $field));
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
            throw $this->invalidShape(sprintf('PHPMD "%s" must be an integer.', $key));
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
            throw $this->invalidShape(sprintf('PHPMD "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse PHPMD JSON output.',
            hint: 'Use --debug to inspect limited raw output snippets.',
            context: ['reason' => $message],
        );
    }
}
