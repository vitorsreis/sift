<?php

declare(strict_types=1);

namespace Sift\Tools\Composer;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use UnexpectedValueException;

final readonly class ComposerAuditParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
    ) {}

    public function parse(string $stdout, string $stderr): ComposerReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $advisories = $this->advisories($document['advisories'] ?? []);
        $abandoned = $this->abandoned($document['abandoned'] ?? []);
        $items = [...$this->advisoryItems($advisories), ...$this->abandonedItems($abandoned)];
        $vulnerablePackages = [];

        foreach ($advisories as $advisory) {
            $vulnerablePackages[$advisory['package']] = true;
        }

        return new ComposerReport(
            summary: [
                'advisories' => count($advisories),
                'vulnerable_packages' => count($vulnerablePackages),
                'abandoned_packages' => count($abandoned),
                'findings' => count($items),
            ],
            items: $items,
            findings: count($items),
        );
    }

    /**
     * @return list<array{package: string, advisory: array<string, mixed>}>
     */
    private function advisories(mixed $value): array
    {
        if ($value === []) {
            return [];
        }

        if (! is_array($value)) {
            throw $this->invalidShape('Composer audit "advisories" must be an object or list.');
        }

        if (array_is_list($value)) {
            $advisories = [];

            foreach ($value as $advisory) {
                $advisory = $this->object($advisory, 'advisories[]');
                $advisories[] = [
                    'package' => $this->stringValue($advisory, 'packageName'),
                    'advisory' => $advisory,
                ];
            }

            return $advisories;
        }

        $advisories = [];

        foreach ($value as $package => $packageAdvisories) {
            if (! is_string($package) || $package === '') {
                throw $this->invalidShape('Composer audit "advisories" must use package names as keys.');
            }

            foreach ($this->list($packageAdvisories, 'advisories.' . $package) as $advisory) {
                $advisories[] = [
                    'package' => $package,
                    'advisory' => $this->object($advisory, 'advisories.' . $package . '[]'),
                ];
            }
        }

        return $advisories;
    }

    /**
     * @param list<array{package: string, advisory: array<string, mixed>}> $advisories
     *
     * @return list<array<string, mixed>>
     */
    private function advisoryItems(array $advisories): array
    {
        $items = [];

        foreach ($advisories as $entry) {
            $advisory = $entry['advisory'];
            $item = [
                'type' => ItemType::Vulnerability->value,
                'package' => $this->optionalString($advisory, 'packageName') ?? $entry['package'],
            ];

            foreach ([
                'advisoryId' => 'advisory_id',
                'cve' => 'cve',
                'title' => 'title',
                'link' => 'link',
                'affectedVersions' => 'affected_versions',
                'reportedAt' => 'reported_at',
            ] as $source => $target) {
                $value = $this->optionalString($advisory, $source);

                if ($value !== null) {
                    $item[$target] = $value;
                }
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array<string, string|null>
     */
    private function abandoned(mixed $value): array
    {
        if ($value === []) {
            return [];
        }

        $packages = $this->object($value, 'abandoned');
        $abandoned = [];

        foreach ($packages as $package => $replacement) {
            if ($package === '') {
                throw $this->invalidShape('Composer audit "abandoned" must use package names as keys.');
            }

            $abandoned[$package] = is_string($replacement) && $replacement !== '' ? $replacement : null;
        }

        return $abandoned;
    }

    /**
     * @param array<string, string|null> $packages
     *
     * @return list<array<string, mixed>>
     */
    private function abandonedItems(array $packages): array
    {
        $items = [];

        foreach ($packages as $package => $replacement) {
            $item = [
                'type' => ItemType::Package->value,
                'package' => $package,
                'abandoned' => true,
                'message' => 'Package is abandoned.',
            ];

            if ($replacement !== null) {
                $item['replacement'] = $replacement;
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
            throw $this->invalidShape(sprintf('Composer audit "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('Composer audit "%s" must use string keys.', $field));
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
            throw $this->invalidShape(sprintf('Composer audit "%s" must be a list.', $field));
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
            throw $this->invalidShape(sprintf('Composer audit "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function optionalString(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse Composer audit JSON output.',
            hint: 'Use --debug to inspect limited raw output snippets.',
            context: ['reason' => $message],
        );
    }
}
