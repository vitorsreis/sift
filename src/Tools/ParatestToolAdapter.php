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

final readonly class ParatestToolAdapter implements ToolAdapterInterface
{
    use DetectsCliOptions;
    use ParsesJunitOutput;
    use ResolvesToolCandidates;

    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function name(): string
    {
        return 'paratest';
    }

    public function installHint(): string
    {
        return 'Install it with: composer require --dev brianium/paratest';
    }

    /**
     * @return list<string>
     */
    public function discoveryCandidates(): array
    {
        return [
            'vendor/bin/paratest.bat',
            'vendor/bin/paratest',
            'paratest',
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
        return [
            'arguments' => $arguments,
            'has_filter' => $this->hasOption($arguments, '--filter'),
            'has_coverage' => $this->hasAnyOption($arguments, ['--coverage', '--coverage-text', '--coverage-clover', '--coverage-html']),
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

        [$arguments, $junitPath] = $this->ensureOptionValue(
            $arguments,
            '--log-junit',
            $this->tempFile('sift-paratest-', '.xml'),
        );

        return new PreparedCommand(
            command: [...$resolved['command_prefix'], ...$arguments],
            cwd: $cwd,
            metadata: [
                ...$context,
                'junit' => $junitPath,
                'temp_files' => [$junitPath],
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
     * @return array{0: list<string>, 1: string}
     */
    private function ensureOptionValue(array $arguments, string $option, string $defaultValue): array
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === $option && isset($arguments[$index + 1])) {
                return [$arguments, $arguments[$index + 1]];
            }

            if (str_starts_with($argument, $option.'=')) {
                return [$arguments, substr($argument, strlen($option) + 1)];
            }
        }

        $arguments[] = $option;
        $arguments[] = $defaultValue;

        return [$arguments, $defaultValue];
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
