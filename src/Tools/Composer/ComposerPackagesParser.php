<?php

declare(strict_types=1);

namespace Sift\Tools\Composer;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use UnexpectedValueException;

final readonly class ComposerPackagesParser
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

        $packages = $this->packages($document);
        $items = [];
        $directDependencies = 0;
        $outdated = 0;
        $abandoned = 0;

        foreach ($packages as $package) {
            $item = $this->packageItem($package);
            $items[] = $item;

            if (($item['direct_dependency'] ?? false) === true) {
                ++$directDependencies;
            }

            if ($this->isOutdated($item)) {
                ++$outdated;
            }

            if (($item['abandoned'] ?? false) === true) {
                ++$abandoned;
            }
        }

        return new ComposerReport(
            summary: [
                'packages' => count($packages),
                'direct_dependencies' => $directDependencies,
                'outdated' => $outdated,
                'abandoned_packages' => $abandoned,
                'findings' => $outdated + $abandoned,
            ],
            items: $items,
            findings: $outdated + $abandoned,
        );
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return list<array<string, mixed>>
     */
    private function packages(array $document): array
    {
        if (isset($document['installed'])) {
            $installed = $this->list($document['installed'], 'installed');
            $packages = [];

            foreach ($installed as $package) {
                $packages[] = $this->object($package, 'installed[]');
            }

            return $packages;
        }

        if (isset($document['name'])) {
            return [$document];
        }

        throw $this->invalidShape('Composer packages output must include "installed" packages.');
    }

    /**
     * @param array<string, mixed> $package
     *
     * @return array<string, mixed>
     */
    private function packageItem(array $package): array
    {
        $item = [
            'type' => ItemType::Package->value,
            'package' => $this->stringValue($package, 'name'),
        ];

        foreach ([
            'version' => 'version',
            'latest' => 'latest',
            'latest-status' => 'latest_status',
            'release-age' => 'release_age',
            'release-date' => 'release_date',
            'latest-release-date' => 'latest_release_date',
            'homepage' => 'homepage',
            'source' => 'source',
            'description' => 'description',
            'warning' => 'warning',
        ] as $source => $target) {
            $value = $this->optionalString($package, $source);

            if ($value !== null) {
                $item[$target] = $value;
            }
        }

        $directDependency = $package['direct-dependency'] ?? null;

        if (is_bool($directDependency)) {
            $item['direct_dependency'] = $directDependency;
        }

        $abandoned = $package['abandoned'] ?? false;
        $item['abandoned'] = $abandoned !== false;

        if (is_string($abandoned) && $abandoned !== '') {
            $item['replacement'] = $abandoned;
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function isOutdated(array $item): bool
    {
        $latestStatus = $item['latest_status'] ?? null;

        if (is_string($latestStatus) && $latestStatus !== '') {
            return $latestStatus !== 'up-to-date';
        }

        $version = $item['version'] ?? null;
        $latest = $item['latest'] ?? null;

        return is_string($version) && is_string($latest) && $latest !== '' && $latest !== $version;
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw $this->invalidShape(sprintf('Composer packages "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('Composer packages "%s" must use string keys.', $field));
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
            throw $this->invalidShape(sprintf('Composer packages "%s" must be a list.', $field));
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
            throw $this->invalidShape(sprintf('Composer packages "%s" must be a non-empty string.', $key));
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
            message: 'Unable to parse Composer packages JSON output.',
            hint: 'Use --debug to inspect limited raw output snippets.',
            context: ['reason' => $message],
        );
    }
}
