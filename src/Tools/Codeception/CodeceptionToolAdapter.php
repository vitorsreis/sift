<?php

declare(strict_types=1);

namespace Sift\Tools\Codeception;

use Sift\Config\ToolConfig;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\CliArguments;
use Sift\Tools\Testing\AbstractTestRunnerToolAdapter;
use Sift\Tools\ToolContext;

final readonly class CodeceptionToolAdapter extends AbstractTestRunnerToolAdapter
{
    protected function name(): string
    {
        return 'codeception';
    }

    #[\Override]
    protected function aliases(): array
    {
        return ['codecept'];
    }

    protected function description(): string
    {
        return 'Codeception full-stack test runner.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/codecept.bat', 'vendor/bin/codecept', 'codecept'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev codeception/codeception';
    }

    protected function defaultContext(): string
    {
        return 'test';
    }

    #[\Override]
    protected function versionCommand(): array
    {
        return ['--version'];
    }

    #[\Override]
    public function context(CliArguments $arguments, string $cwd): ToolContext
    {
        return new ToolContext(
            toolName: $this->name(),
            subcommand: 'run',
            userArgs: $arguments->toolArguments(),
            cwd: $cwd,
            raw: $arguments->siftOption('raw') === true,
            debug: $arguments->siftOption('debug') === true,
            filter: $arguments->value('--filter') ?? $arguments->value('--grep'),
            coverage: $arguments->hasAny([
                '--coverage',
                '--coverage-text',
                '--coverage-xml',
                '--coverage-html',
                '--coverage-cobertura',
                '--coverage-crap4j',
                '--coverage-phpunit',
            ]),
            coverageMin: $arguments->value('--min') === null ? null : $arguments->floatValue('--min'),
            mode: 'test',
        );
    }

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        $arguments = $this->withoutRunPseudoSubcommand($context->userArgs());

        if ($context->raw()) {
            return $this->prepareBaseCommand($tool, $context, $config, ['run', ...$arguments]);
        }

        $arguments = $this->withoutMinimum($arguments);
        $arguments = $this->withoutValuelessOption($arguments, '--xml');
        $arguments = $this->withoutValuelessOption($arguments, '--coverage-xml');

        return $this->commandFactory()->prepare(
            toolName: $this->name(),
            tool: $tool,
            context: $context,
            config: $config,
            arguments: ['run', ...$arguments],
            junitOption: '--xml',
            coverageOption: '--coverage-xml',
            inlineOutputOptions: true,
        );
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function withoutRunPseudoSubcommand(array $arguments): array
    {
        return ($arguments[0] ?? null) === 'run' ? array_slice($arguments, 1) : $arguments;
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function withoutMinimum(array $arguments): array
    {
        $normalized = [];
        $skipNext = false;

        foreach ($arguments as $index => $argument) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if (str_starts_with($argument, '--min=')) {
                continue;
            }

            if ($argument === '--min') {
                $next = $arguments[$index + 1] ?? null;
                $skipNext = is_string($next) && $this->isValueToken($next);

                continue;
            }

            $normalized[] = $argument;
        }

        return $normalized;
    }

    private function isValueToken(string $token): bool
    {
        return ! str_starts_with($token, '-') || preg_match('/^-?(?:\d+(?:\.\d+)?|\.\d+)$/', $token) === 1;
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function withoutValuelessOption(array $arguments, string $option): array
    {
        $normalized = [];

        foreach ($arguments as $index => $argument) {
            if ($argument === $option) {
                $next = $arguments[$index + 1] ?? null;

                if ($next === null) {
                    continue;
                }

                if (str_starts_with((string) $next, '-')) {
                    continue;
                }
            }

            if ($argument === $option . '=') {
                continue;
            }

            $normalized[] = $argument;
        }

        return $normalized;
    }
}
