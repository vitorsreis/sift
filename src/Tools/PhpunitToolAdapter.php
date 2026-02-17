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
use Sift\Tools\Concerns\EnsuresCommandOptionValues;
use Sift\Tools\Concerns\ParsesJunitOutput;
use Sift\Tools\Concerns\ResolvesToolCandidates;

final readonly class PhpunitToolAdapter implements ToolAdapterInterface
{
    use DetectsCliOptions;
    use EnsuresCommandOptionValues;
    use ParsesJunitOutput;
    use ResolvesToolCandidates;

    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function name(): string
    {
        return 'phpunit';
    }

    public function installHint(): string
    {
        return 'Install it with: composer require --dev phpunit/phpunit';
    }

    /**
     * @return list<string>
     */
    public function discoveryCandidates(): array
    {
        return [
            'vendor/bin/phpunit.bat',
            'vendor/bin/phpunit',
            'phpunit',
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
            'has_coverage' => $this->hasAnyOption($arguments, ['--coverage-text', '--coverage-clover', '--coverage-html']),
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

        [$arguments, $junitPath, $createdJunit] = $this->ensureOptionValue(
            $arguments,
            '--log-junit',
            $this->tempFile('sift-phpunit-', '.xml'),
        );

        return new PreparedCommand(
            command: [...$resolved['command_prefix'], ...$arguments],
            cwd: $cwd,
            metadata: [
                ...$context,
                'junit' => $junitPath,
                'temp_files' => $createdJunit ? [$junitPath] : [],
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
}
