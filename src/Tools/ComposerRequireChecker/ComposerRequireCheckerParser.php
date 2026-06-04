<?php

declare(strict_types=1);

namespace Sift\Tools\ComposerRequireChecker;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use UnexpectedValueException;

final readonly class ComposerRequireCheckerParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
    ) {}

    public function parse(string $stdout, string $stderr): ComposerRequireCheckerReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $unknownSymbols = $this->objectOrEmptyList($document['unknown-symbols'] ?? [], 'unknown-symbols');
        $result = $this->items($unknownSymbols);

        return new ComposerRequireCheckerReport(
            summary: [
                'unknown_symbols' => count($result['items']),
                'packages' => count($result['packages']),
            ],
            items: $result['items'],
            unknownSymbols: count($result['items']),
        );
    }

    /**
     * @param array<string, mixed> $unknownSymbols
     *
     * @return array{items: list<array<string, mixed>>, packages: array<string, true>}
     */
    private function items(array $unknownSymbols): array
    {
        $items = [];
        $packages = [];

        foreach ($unknownSymbols as $symbol => $details) {
            $detail = $this->symbolDetail($details, $symbol);

            foreach ($detail['packages'] as $package) {
                $packages[$package] = true;
            }

            $item = [
                'type' => ItemType::MissingDependency->value,
                'symbol' => $symbol,
                'packages' => $detail['packages'],
            ];

            if ($detail['packages'] !== []) {
                $item['package'] = $detail['packages'][0];
            }

            foreach (['file', 'line'] as $optionalField) {
                if (array_key_exists($optionalField, $detail)) {
                    $item[$optionalField] = $detail[$optionalField];
                }
            }

            $items[] = $item;
        }

        return [
            'items' => $items,
            'packages' => $packages,
        ];
    }

    /**
     * @return array{packages: list<string>, file?: string, line?: int}
     */
    private function symbolDetail(mixed $details, string $symbol): array
    {
        $details = $this->list($details, sprintf('unknown-symbols.%s', $symbol));
        $packages = [];
        $file = null;
        $line = null;

        foreach ($details as $detail) {
            if (is_string($detail) && $detail !== '') {
                $packages[] = $detail;
                continue;
            }

            $detail = $this->object($detail, sprintf('unknown-symbols.%s[]', $symbol));
            $packages[] = $this->stringValue($detail, 'package');

            if ($file === null && is_string($detail['file'] ?? null) && $detail['file'] !== '') {
                $file = $detail['file'];
            }

            if ($line === null && is_int($detail['line'] ?? null)) {
                $line = $detail['line'];
            }
        }

        $packages = array_values(array_unique($packages));

        $result = ['packages' => $packages];

        if ($file !== null) {
            $result['file'] = $file;
        }

        if ($line !== null) {
            $result['line'] = $line;
        }

        return $result;
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
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw $this->invalidShape(sprintf('Composer Require Checker "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('Composer Require Checker "%s" must use string keys.', $field));
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
            throw $this->invalidShape(sprintf('Composer Require Checker "%s" must be a list.', $field));
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
            throw $this->invalidShape(sprintf('Composer Require Checker "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse Composer Require Checker JSON output.',
            hint: 'Use --debug to inspect limited raw output snippets.',
            context: ['reason' => $message],
        );
    }
}
