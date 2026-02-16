<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;
use Sift\Tools\Concerns\DetectsCliOptions;
use Sift\Tools\Concerns\ParsesJunitOutput;
use Sift\Tools\Concerns\ResolvesToolCandidates;

final readonly class PestToolAdapter implements ToolAdapterInterface
{
    use DetectsCliOptions;
    use ParsesJunitOutput;
    use ResolvesToolCandidates;

    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function name(): string
    {
        return 'pest';
    }

    public function installHint(): string
    {
        return 'Install it with: composer require --dev pestphp/pest';
    }

    /**
     * @return list<string>
     */
    public function discoveryCandidates(): array
    {
        return [
            'vendor/bin/pest.bat',
            'vendor/bin/pest',
            'pest',
        ];
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
        $coverageMin = $this->floatOptionValue($arguments, '--min');

        return [
            'arguments' => $arguments,
            'has_filter' => $this->hasOption($arguments, '--filter'),
            'has_coverage' => $coverageMin !== null || $this->hasAnyOption($arguments, ['--coverage', '--coverage-text', '--coverage-clover', '--coverage-html']),
            'coverage_min' => $coverageMin,
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

        $tempFiles = [];

        [$arguments, $junitPath, $createdJunit] = $this->ensureOptionValue(
            $arguments,
            '--log-junit',
            $this->tempFile('sift-pest-', '.xml'),
        );

        if ($createdJunit) {
            $tempFiles[] = $junitPath;
        }

        $coveragePath = null;

        if (($context['has_coverage'] ?? false) === true) {
            [$arguments, $coveragePath, $createdCoverage] = $this->ensureOptionValue(
                $arguments,
                '--coverage-clover',
                $this->tempFile('sift-pest-coverage-', '.xml'),
            );

            if ($createdCoverage) {
                $tempFiles[] = $coveragePath;
            }
        }

        return new PreparedCommand(
            command: [...$resolved['command_prefix'], ...$arguments],
            cwd: $cwd,
            metadata: [
                ...$context,
                'junit' => $junitPath,
                'coverage_clover' => $coveragePath,
                'temp_files' => $tempFiles,
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
        return $this->parseJunitOutput($executionResult, $preparedCommand, $context);
    }

    /**
     * @param  list<string>  $arguments
     * @return array{0: list<string>, 1: string, 2: bool}
     */
    private function ensureOptionValue(array $arguments, string $option, string $defaultValue): array
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === $option && isset($arguments[$index + 1])) {
                return [$arguments, $arguments[$index + 1], false];
            }

            if (str_starts_with($argument, $option.'=')) {
                return [$arguments, substr($argument, strlen($option) + 1), false];
            }
        }

        $arguments[] = $option;
        $arguments[] = $defaultValue;

        return [$arguments, $defaultValue, true];
    }

    private function tempFile(string $prefix, string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw UserFacingException::parseFailure($this->name(), 'Unable to allocate temporary file.');
        }

        $target = $path.$extension;
        @rename($path, $target);

        return $target;
    }
}
