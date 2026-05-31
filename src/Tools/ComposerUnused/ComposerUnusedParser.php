<?php

declare(strict_types=1);

namespace Sift\Tools\ComposerUnused;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use UnexpectedValueException;

final readonly class ComposerUnusedParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
    ) {}

    public function parse(string $stdout, string $stderr): ComposerUnusedReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $usedPackages = $this->list($document['used-packages'] ?? [], 'used-packages');
        $unusedPackages = $this->list($document['unused-packages'] ?? [], 'unused-packages');
        $ignoredPackages = $this->list($document['ignored-packages'] ?? [], 'ignored-packages');
        $zombieExclusions = $this->list($document['zombie-exclusions'] ?? [], 'zombie-exclusions');

        return new ComposerUnusedReport(
            summary: [
                'used_packages' => count($usedPackages),
                'unused_packages' => count($unusedPackages),
                'ignored_packages' => count($ignoredPackages),
                'zombie_exclusions' => count($zombieExclusions),
            ],
            items: [
                ...$this->unusedItems($unusedPackages),
                ...$this->zombieExclusionItems($zombieExclusions),
            ],
            unusedPackages: count($unusedPackages),
            findings: count($unusedPackages) + count($zombieExclusions),
        );
    }

    /**
     * @param list<mixed> $packages
     *
     * @return list<array<string, mixed>>
     */
    private function unusedItems(array $packages): array
    {
        $items = [];

        foreach ($packages as $package) {
            $items[] = [
                'type' => ItemType::UnusedDependency->value,
                ...$this->packageFields($package),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function packageFields(mixed $package): array
    {
        if (is_string($package) && $package !== '') {
            return ['package' => $package];
        }

        $package = $this->object($package, 'unused-packages[]');

        $fields = ['package' => $this->stringValue($package, 'name')];

        foreach (['path', 'reason'] as $optionalStringField) {
            $value = $package[$optionalStringField] ?? null;

            if (is_string($value) && $value !== '') {
                $fields[$optionalStringField] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param list<mixed> $exclusions
     *
     * @return list<array<string, mixed>>
     */
    private function zombieExclusionItems(array $exclusions): array
    {
        $items = [];

        foreach ($exclusions as $exclusion) {
            if (! is_string($exclusion) || $exclusion === '') {
                throw $this->invalidShape('Composer Unused "zombie-exclusions" must contain only non-empty strings.');
            }

            $items[] = [
                'type' => ItemType::Warning->value,
                'message' => 'Unused composer-unused exclusion.',
                'exclusion' => $exclusion,
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
            throw $this->invalidShape(sprintf('Composer Unused "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('Composer Unused "%s" must use string keys.', $field));
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
            throw $this->invalidShape(sprintf('Composer Unused "%s" must be a list.', $field));
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
            throw $this->invalidShape(sprintf('Composer Unused "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse Composer Unused JSON output.',
            hint: 'Use --debug to inspect limited raw output snippets.',
            context: ['reason' => $message],
        );
    }
}
