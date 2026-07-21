<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
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

    /**
     * @param list<string> $arguments
     * @param list<string> $values
     * @return list<string>
     */
    protected function withoutArguments(array $arguments, array $values): array
    {
        return array_values(array_filter(
            $arguments,
            static fn(string $argument): bool => ! in_array($argument, $values, true),
        ));
    }

    /**
     * @param list<string> $arguments
     * @param list<string> $options
     * @return list<string>
     */
    protected function withoutOptions(array $arguments, array $options): array
    {
        $remaining = [];
        $skipNext = false;

        foreach ($arguments as $index => $argument) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            foreach ($options as $option) {
                if ($argument === $option) {
                    $next = $arguments[$index + 1] ?? null;
                    $skipNext = is_string($next) && ! str_starts_with($next, '-');
                    continue 2;
                }

                if (str_starts_with($argument, $option . '=')) {
                    continue 2;
                }
            }

            $remaining[] = $argument;
        }

        return $remaining;
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

    /**
     * @return array<string, mixed>
     */
    protected function initialConfig(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function machineOutputRules(): array
    {
        return [];
    }

    /**
     * @return array{filter: string|null, coverage: bool, coverage_min: float|null, mode: string|null, dry_run: bool, subcommand: string|null, warnings: list<string>}
     */
    protected function commonMeta(ToolContext $context): array
    {
        return [
            'filter' => $context->filter(),
            'coverage' => $context->coverage(),
            'coverage_min' => $context->coverageMin(),
            'mode' => $context->mode(),
            'dry_run' => $context->dryRun(),
            'subcommand' => $context->subcommand(),
            'warnings' => $context->warnings(),
        ];
    }

    protected function resolveStatus(
        ExecutionResult $execution,
        int $findings = 0,
        bool $changed = false,
        ?ErrorCode $errorCode = null,
    ): RunStatus {
        return (new StatusDecider())->decide($execution, $findings, $changed, $errorCode);
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
