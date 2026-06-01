<?php

declare(strict_types=1);

namespace Sift\Tools\Pint;

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\MutationPolicy;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class PintToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private PintParser $parser = new PintParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'pint';
    }

    protected function description(): string
    {
        return 'Laravel Pint code style formatter.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/pint.bat', 'vendor/bin/pint', 'pint'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev laravel/pint';
    }

    protected function defaultContext(): string
    {
        return 'style';
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
            arguments: $context->repair()
                ? $this->repairArguments($context->userArgs())
                : $this->safeArguments($context->userArgs()),
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        $report = $this->parser->parse($execution->stdout(), $execution->stderr(), $context->cwd(), $context->repair());

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->statusDecider->decide(
                execution: $execution,
                findings: $report->findings(),
                changed: $report->changed(),
            ),
            summary: $report->summary(),
            items: $report->items(),
        );
    }

    /**
     * @param list<string> $userArguments
     * @return list<string>
     */
    private function safeArguments(array $userArguments): array
    {
        $arguments = [];

        if (! $this->hasOption($userArguments, '--test')) {
            $arguments[] = '--test';
        }

        if (! $this->hasOption($userArguments, '--format')) {
            $arguments[] = '--format=json';
        }

        return [...$arguments, ...$userArguments];
    }

    /**
     * @param list<string> $userArguments
     * @return list<string>
     */
    private function repairArguments(array $userArguments): array
    {
        $arguments = [];

        if (! $this->hasOption($userArguments, '--repair')) {
            $arguments[] = '--repair';
        }

        if (! $this->hasOption($userArguments, '--format')) {
            $arguments[] = '--format=json';
        }

        return [...$arguments, ...$userArguments];
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
}
