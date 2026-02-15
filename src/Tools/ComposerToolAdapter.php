<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;
use Sift\Tools\Concerns\DecodesJsonOutput;
use Sift\Tools\Concerns\DetectsCliOptions;
use Sift\Tools\Concerns\ResolvesToolCandidates;

final readonly class ComposerToolAdapter implements ToolAdapterInterface
{
    use DecodesJsonOutput;
    use DetectsCliOptions;
    use ResolvesToolCandidates;

    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function name(): string
    {
        return 'composer';
    }

    public function installHint(): string
    {
        return 'Install Composer first and make sure the `composer` command is available.';
    }

    /**
     * @return list<string>
     */
    public function discoveryCandidates(): array
    {
        return ['composer'];
    }

    /**
     * @return array<string, mixed>
     */
    public function initConfig(): array
    {
        return [
            'enabled' => true,
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    public function detectContext(array $arguments): array
    {
        $subcommand = $this->subcommand($arguments);

        return [
            'arguments' => $arguments,
            'subcommand' => $subcommand,
            'mode' => $this->mode($arguments, $subcommand),
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, mixed>  $context
     */
    public function prepare(string $cwd, array $arguments, array $context): PreparedCommand
    {
        $resolved = $this->toolLocator->locate($cwd, $this->resolveCandidates($context, $this->discoveryCandidates()));

        if ($resolved === null) {
            throw UserFacingException::toolNotInstalled($this->name(), $this->installHint());
        }

        if (! $this->hasAnyOption($arguments, ['--format', '-f'])) {
            $arguments[] = '--format=json';
        }

        $subcommand = is_string($context['subcommand'] ?? null) ? (string) $context['subcommand'] : $this->subcommand($arguments);

        return new PreparedCommand(
            command: [...$resolved['command_prefix'], ...$arguments],
            cwd: $cwd,
            metadata: [
                ...$context,
                'subcommand' => $subcommand,
                'mode' => is_string($context['mode'] ?? null) ? (string) $context['mode'] : $this->mode($arguments, $subcommand),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function parse(
        ExecutionResult $executionResult,
        PreparedCommand $preparedCommand,
        array $context,
    ): NormalizedResult {
        $decoded = $this->decodeJsonOutput($executionResult, allowNoisy: true);

        if (! is_array($decoded)) {
            throw UserFacingException::parseFailure($this->name(), 'Unable to parse Composer JSON output.');
        }

        $subcommand = is_string($preparedCommand->metadata['subcommand'] ?? null)
            ? (string) $preparedCommand->metadata['subcommand']
            : (is_string($context['subcommand'] ?? null) ? (string) $context['subcommand'] : '');
        $mode = is_string($preparedCommand->metadata['mode'] ?? null)
            ? (string) $preparedCommand->metadata['mode']
            : (is_string($context['mode'] ?? null) ? (string) $context['mode'] : $subcommand);

        return match ($subcommand) {
            'audit' => $this->parseAudit($decoded, $executionResult, $preparedCommand),
            'licenses' => $this->parseLicenses($decoded, $executionResult, $preparedCommand),
            'outdated', 'show' => $this->parsePackages($decoded, $executionResult, $preparedCommand, $subcommand, $mode),
            default => throw UserFacingException::parseFailure($this->name(), 'Unable to determine the Composer subcommand context.'),
        };
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function parseAudit(array $decoded, ExecutionResult $executionResult, PreparedCommand $preparedCommand): NormalizedResult
    {
        $advisories = is_array($decoded['advisories'] ?? null) ? $decoded['advisories'] : [];
        $items = [];

        foreach ($advisories as $package => $packageAdvisories) {
            if (! is_array($packageAdvisories)) {
                continue;
            }

            foreach ($packageAdvisories as $advisory) {
                if (! is_array($advisory)) {
                    continue;
                }

                $items[] = [
                    'package' => (string) $package,
                    'severity' => (string) ($advisory['severity'] ?? 'unknown'),
                    'advisory_id' => (string) ($advisory['advisoryId'] ?? ''),
                    'title' => (string) ($advisory['title'] ?? ''),
                    'cve' => (string) ($advisory['cve'] ?? ''),
                    'link' => (string) ($advisory['link'] ?? ''),
                ];
            }
        }

        return new NormalizedResult(
            tool: $this->name(),
            status: $items === [] ? ($executionResult->exitCode === 0 ? 'passed' : 'error') : 'failed',
            summary: [
                'vulnerabilities' => count($items),
                'packages' => count($advisories),
            ],
            items: $items,
            meta: $this->meta($executionResult, $preparedCommand, 'audit', 'audit'),
        );
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function parseLicenses(array $decoded, ExecutionResult $executionResult, PreparedCommand $preparedCommand): NormalizedResult
    {
        $items = [];
        $uniqueLicenses = [];

        foreach ($this->normalizeDependencyLicenses($decoded['dependencies'] ?? []) as $item) {
            $items[] = $item;

            foreach ($item['licenses'] as $license) {
                $uniqueLicenses[$license] = true;
            }
        }

        $rootLicenses = $this->normalizeLicenses($decoded['license'] ?? $decoded['licenses'] ?? []);

        foreach ($rootLicenses as $license) {
            $uniqueLicenses[$license] = true;
        }

        return new NormalizedResult(
            tool: $this->name(),
            status: $executionResult->exitCode === 0 ? 'passed' : 'error',
            summary: [
                'dependencies' => count($items),
                'licenses' => count($uniqueLicenses),
            ],
            items: $items,
            extra: [
                'root_package' => [
                    'name' => (string) ($decoded['name'] ?? ''),
                    'version' => (string) ($decoded['version'] ?? ''),
                    'licenses' => $rootLicenses,
                ],
            ],
            meta: $this->meta($executionResult, $preparedCommand, 'licenses', 'licenses'),
        );
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function parsePackages(
        array $decoded,
        ExecutionResult $executionResult,
        PreparedCommand $preparedCommand,
        string $subcommand,
        string $mode,
    ): NormalizedResult {
        $installed = $this->installedPackages($decoded);
        $items = [];
        $outdated = 0;
        $abandoned = 0;

        foreach ($installed as $package) {
            $normalized = $this->normalizePackage($package);

            if ($normalized === null) {
                continue;
            }

            $isOutdated = $this->isOutdated($normalized);

            if ($isOutdated) {
                $outdated++;
            }

            if (($normalized['abandoned'] ?? false) === true) {
                $abandoned++;
            }

            if ($mode === 'outdated' && ! $isOutdated) {
                continue;
            }

            $items[] = $normalized;
        }

        $status = $mode === 'outdated'
            ? ($outdated > 0 ? 'failed' : ($executionResult->exitCode === 0 ? 'passed' : 'error'))
            : ($executionResult->exitCode === 0 ? 'passed' : 'error');

        return new NormalizedResult(
            tool: $this->name(),
            status: $status,
            summary: [
                'packages' => count($installed),
                'outdated' => $outdated,
                'abandoned' => $abandoned,
            ],
            items: $items,
            meta: $this->meta($executionResult, $preparedCommand, $subcommand, $mode),
        );
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    private function installedPackages(array $decoded): array
    {
        if (isset($decoded['installed']) && is_array($decoded['installed'])) {
            return array_values(array_filter(
                $decoded['installed'],
                static fn (mixed $package): bool => is_array($package),
            ));
        }

        if (array_is_list($decoded)) {
            return array_values(array_filter(
                $decoded,
                static fn (mixed $package): bool => is_array($package),
            ));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>|null
     */
    private function normalizePackage(array $package): ?array
    {
        $name = trim((string) ($package['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $abandoned = $package['abandoned'] ?? false;
        $replacement = '';

        if (is_string($abandoned) && $abandoned !== '') {
            $replacement = $abandoned;
            $abandoned = true;
        }

        if ($replacement === '' && is_string($package['replacement'] ?? null)) {
            $replacement = (string) $package['replacement'];
        }

        return [
            'package' => $name,
            'version' => (string) ($package['version'] ?? ''),
            'latest' => (string) ($package['latest'] ?? ''),
            'latest_status' => (string) ($package['latest-status'] ?? $package['latest_status'] ?? ''),
            'description' => (string) ($package['description'] ?? ''),
            'abandoned' => (bool) $abandoned,
            'replacement' => $replacement,
        ];
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function isOutdated(array $package): bool
    {
        $status = trim((string) ($package['latest_status'] ?? ''));

        if ($status !== '' && $status !== 'up-to-date') {
            return true;
        }

        $latest = trim((string) ($package['latest'] ?? ''));
        $version = trim((string) ($package['version'] ?? ''));

        return $latest !== '' && $version !== '' && $latest !== $version;
    }

    /**
     * @return list<array{package: string, licenses: list<string>}>
     */
    private function normalizeDependencyLicenses(mixed $dependencies): array
    {
        if (! is_array($dependencies)) {
            return [];
        }

        $items = [];

        foreach ($dependencies as $package => $licenses) {
            if (! is_string($package) || $package === '') {
                if (! is_array($licenses)) {
                    continue;
                }

                $package = trim((string) ($licenses['name'] ?? ''));

                if ($package === '') {
                    continue;
                }

                $licenses = $licenses['license'] ?? $licenses['licenses'] ?? [];
            }

            $items[] = [
                'package' => $package,
                'licenses' => $this->normalizeLicenses($licenses),
            ];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function normalizeLicenses(mixed $licenses): array
    {
        if (is_string($licenses) && trim($licenses) !== '') {
            return [trim($licenses)];
        }

        if (! is_array($licenses)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (mixed $license): string => trim((string) $license),
                $licenses,
            ),
            static fn (string $license): bool => $license !== '',
        ));
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    private function meta(ExecutionResult $executionResult, PreparedCommand $preparedCommand, string $subcommand, string $mode): array
    {
        return [
            'exit_code' => $executionResult->exitCode,
            'duration' => $executionResult->duration,
            'command' => $preparedCommand->command,
            'subcommand' => $subcommand,
            'mode' => $mode,
        ];
    }

    /**
     * @param  list<string>  $arguments
     */
    private function subcommand(array $arguments): string
    {
        foreach ($arguments as $argument) {
            if ($argument !== '' && ! str_starts_with($argument, '-')) {
                return $argument;
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $arguments
     */
    private function mode(array $arguments, string $subcommand): string
    {
        if ($subcommand === 'show' && $this->hasAnyOption($arguments, ['--outdated', '-o'])) {
            return 'outdated';
        }

        return $subcommand !== '' ? $subcommand : 'show';
    }
}
