<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Config\ToolConfig;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;

abstract readonly class AbstractCliToolAdapter implements ToolAdapter
{
    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            aliases: $this->aliases(),
            description: $this->description(),
            binaryCandidates: $this->binaryCandidates(),
            installHint: $this->installHint(),
            defaultContext: $this->defaultContext(),
            versionCommand: $this->versionCommand(),
            mutationPolicy: $this->mutationPolicy(),
            repairCommand: $this->repairCommand(),
        );
    }

    public function context(CliArguments $arguments, string $cwd): ToolContext
    {
        $repair = $this->repairRequested($arguments);

        return new ToolContext(
            toolName: $this->name(),
            userArgs: $repair ? $this->withoutRepairFlag($arguments->toolArguments()) : $arguments->toolArguments(),
            cwd: $cwd,
            raw: $arguments->siftOption('raw') === true,
            debug: $arguments->siftOption('debug') === true,
            repair: $repair,
            dryRun: $arguments->has('--dry-run'),
            filter: $arguments->value('--filter'),
            coverage: $arguments->hasAny(['--coverage', '--coverage-text', '--coverage-clover', '--coverage-html']),
            coverageMin: $arguments->value('--min') === null ? null : $arguments->floatValue('--min'),
        );
    }

    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        $arguments = $context->repair()
            ? [...$this->repairCommand(), ...$context->userArgs()]
            : [...$this->defaultArguments($context), ...$context->userArgs()];

        return $this->prepareBaseCommand($tool, $context, $config, $arguments);
    }

    /**
     * @param list<string> $arguments
     */
    protected function prepareBaseCommand(
        LocatedTool $tool,
        ToolContext $context,
        ToolConfig $config,
        array $arguments,
    ): PreparedCommand {
        return new PreparedCommand(
            tool: $this->name(),
            binary: $tool->binary(),
            arguments: $arguments,
            cwd: $context->cwd(),
            timeout: $config->timeout(),
        );
    }

    abstract protected function name(): string;

    /**
     * @return list<string>
     */
    protected function aliases(): array
    {
        return [];
    }

    abstract protected function description(): string;

    /**
     * @return non-empty-list<string>
     */
    abstract protected function binaryCandidates(): array;

    abstract protected function installHint(): string;

    abstract protected function defaultContext(): string;

    /**
     * @return list<string>
     */
    protected function versionCommand(): array
    {
        return [];
    }

    protected function mutationPolicy(): MutationPolicy
    {
        return MutationPolicy::Never;
    }

    /**
     * @return list<string>
     */
    protected function repairCommand(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function defaultArguments(ToolContext $context): array
    {
        return [];
    }

    private function repairRequested(CliArguments $arguments): bool
    {
        return $this->mutationPolicy() === MutationPolicy::RepairFlag
            && in_array('--repair', $arguments->toolArguments(), true);
    }

    /**
     * @param list<string> $arguments
     * @return list<string>
     */
    private function withoutRepairFlag(array $arguments): array
    {
        return array_values(array_filter(
            $arguments,
            static fn(string $argument): bool => $argument !== '--repair',
        ));
    }
}
