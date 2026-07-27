<?php

declare(strict_types=1);

namespace Sift\Tools\Rector;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\MutationPolicy;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class RectorToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private RectorParser $parser = new RectorParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'rector';
    }

    protected function description(): string
    {
        return 'Rector refactoring analyser and fixer.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/rector.bat', 'vendor/bin/rector', 'rector'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev rector/rector';
    }

    protected function defaultContext(): string
    {
        return 'refactor';
    }

    #[\Override]
    protected function versionCommand(): array
    {
        return ['--version'];
    }

    #[\Override]
    protected function mutationPolicy(): MutationPolicy
    {
        return MutationPolicy::RepairFlag;
    }

    #[\Override]
    protected function repairCommand(): array
    {
        return ['--repair'];
    }

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        return $this->prepareBaseCommand(
            tool: $tool,
            context: $context,
            config: $config,
            arguments: $this->arguments($context->userArgs(), ! $context->raw(), $context->repair()),
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        $report = $this->parser->parse($execution->stdout(), $execution->stderr(), $context->cwd());

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->status($execution, $context, $report),
            summary: $report->summary(),
            items: $report->items(),
        );
    }

    /**
     * @param list<string> $userArguments
     * @return list<string>
     */
    private function arguments(array $userArguments, bool $machineOutput, bool $repair): array
    {
        $arguments = [];
        $remaining = $userArguments;
        $first = $remaining[0] ?? null;

        if ($first === 'process') {
            $arguments[] = $first;
            $remaining = array_slice($remaining, 1);
        } elseif (is_string($first) && $this->isUnsupportedSubcommand($first)) {
            throw new InvalidUsageException('Rector adapter supports only the "process" command.');
        } else {
            $arguments[] = 'process';
        }

        if (! $repair && ! $this->hasOption($remaining, '--dry-run')) {
            $arguments[] = '--dry-run';
        }

        if (! $machineOutput) {
            return [...$arguments, ...$remaining];
        }

        $remaining = $this->withoutArguments($remaining, [
            '--no-progress-bar',
            '--ansi',
            '--no-ansi',
        ]);

        if (! $this->hasOption($remaining, '--output-format')) {
            $arguments[] = '--output-format=json';
        }

        $arguments[] = '--no-progress-bar';
        $arguments[] = '--no-ansi';

        return [...$arguments, ...$remaining];
    }

    private function status(ExecutionResult $execution, ToolContext $context, RectorReport $report): RunStatus
    {
        if ($report->errors() > 0) {
            return RunStatus::Error;
        }

        if ($context->repair()) {
            return $this->statusDecider->decide($execution, changed: $report->changedFiles() > 0);
        }

        return $this->statusDecider->decide($execution, findings: $report->changedFiles());
    }

    /**
     * @param list<string> $arguments
     */
    private function hasOption(array $arguments, string $option): bool
    {
        foreach ($arguments as $argument) {
            if ($argument === $option || str_starts_with($argument, $option . '=')) {
                return true;
            }
        }

        return false;
    }

    private function isUnsupportedSubcommand(string $argument): bool
    {
        return in_array($argument, ['list', 'init', 'setup'], true);
    }
}
