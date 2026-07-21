<?php

declare(strict_types=1);

namespace Sift\Tools\ParallelLint;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class ParallelLintToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private ParallelLintParser $parser = new ParallelLintParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'parallel-lint';
    }

    protected function description(): string
    {
        return 'PHP Parallel Lint syntax checker.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/parallel-lint.bat', 'vendor/bin/parallel-lint', 'parallel-lint'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev php-parallel-lint/php-parallel-lint';
    }

    protected function defaultContext(): string
    {
        return 'syntax';
    }

    #[\Override]
    protected function versionCommand(): array
    {
        return ['--version'];
    }

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        return $this->prepareBaseCommand(
            tool: $tool,
            context: $context,
            config: $config,
            arguments: $context->raw() ? $context->userArgs() : $this->jsonArguments($context->userArgs()),
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        $report = $this->parser->parse($execution->stdout(), $execution->stderr(), $context->cwd());

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->statusDecider->decide($execution, findings: $report->errors()),
            summary: $report->summary(),
            items: $report->items(),
        );
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function jsonArguments(array $arguments): array
    {
        if (in_array('--checkstyle', $arguments, true) || in_array('--gitlab', $arguments, true)) {
            throw new InvalidUsageException('Parallel Lint adapter requires JSON output outside raw mode.');
        }

        $defaults = [];
        $arguments = $this->withoutArguments($arguments, [
            '--no-progress',
            '--colors',
            '--no-colors',
        ]);

        if (! $this->hasPathArgument($arguments)) {
            $defaults[] = '.';
        }

        if (! in_array('--json', $arguments, true)) {
            $defaults[] = '--json';
        }

        $defaults[] = '--no-progress';
        $defaults[] = '--no-colors';

        return [...$defaults, ...$arguments];
    }

    /**
     * @param list<string> $arguments
     */
    private function hasPathArgument(array $arguments): bool
    {
        $optionsWithValue = ['-p', '-e', '-j', '--exclude', '--git', '--syntax-error-callback'];
        $skipNext = false;

        foreach ($arguments as $argument) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            if (str_starts_with($argument, '--exclude=')) {
                continue;
            }

            if (in_array($argument, $optionsWithValue, true)) {
                $skipNext = true;
                continue;
            }

            if (! str_starts_with($argument, '-')) {
                return true;
            }
        }

        return false;
    }
}
