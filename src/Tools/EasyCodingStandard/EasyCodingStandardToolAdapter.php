<?php

declare(strict_types=1);

namespace Sift\Tools\EasyCodingStandard;

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

final readonly class EasyCodingStandardToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private EasyCodingStandardParser $parser = new EasyCodingStandardParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'ecs';
    }

    #[\Override]
    protected function aliases(): array
    {
        return ['easy-coding-standard'];
    }

    protected function description(): string
    {
        return 'Easy Coding Standard style checker and fixer.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/ecs.bat', 'vendor/bin/ecs', 'ecs'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev symplify/easy-coding-standard';
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
        if ($context->raw()) {
            return $this->prepareBaseCommand($tool, $context, $config, $context->userArgs());
        }

        $arguments = $this->withoutCheckCommand($context->userArgs());
        $arguments = $this->withoutArguments($arguments, ['--no-progress-bar']);

        if (! $context->repair() && $this->hasOption($arguments, '--fix')) {
            throw new InvalidUsageException('ECS modifies files with --fix; pass --repair instead.');
        }

        $format = $this->optionValue($arguments, '--output-format');

        if ($format !== null && strtolower($format) !== 'json') {
            throw new InvalidUsageException('ECS adapter requires JSON output outside raw mode.');
        }

        $defaults = [];

        if ($context->repair() && ! $this->hasOption($arguments, '--fix')) {
            $defaults[] = '--fix';
        }

        if ($format === null) {
            $defaults[] = '--output-format=json';
        }

        $defaults[] = '--no-progress-bar';

        return $this->prepareBaseCommand(
            tool: $tool,
            context: $context,
            config: $config,
            arguments: [...$defaults, ...$arguments],
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

    private function status(ExecutionResult $execution, ToolContext $context, EasyCodingStandardReport $report): RunStatus
    {
        if ($context->repair() && $report->errors() === 0) {
            return $this->statusDecider->decide(
                execution: $execution,
                changed: $report->diffs() > 0,
            );
        }

        return $this->statusDecider->decide($execution, findings: $report->findings());
    }

    /**
     * @param list<string> $arguments
     * @return list<string>
     */
    private function withoutCheckCommand(array $arguments): array
    {
        if (($arguments[0] ?? null) === 'check') {
            return array_slice($arguments, 1);
        }

        $first = $arguments[0] ?? null;

        if (is_string($first) && in_array($first, ['list', 'list-checkers'], true)) {
            throw new InvalidUsageException('ECS adapter supports only style checks and fixes.');
        }

        return $arguments;
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

    /**
     * @param list<string> $arguments
     */
    private function optionValue(array $arguments, string $option): ?string
    {
        foreach ($arguments as $index => $argument) {
            if (str_starts_with($argument, $option . '=')) {
                return substr($argument, strlen($option) + 1);
            }

            if ($argument === $option) {
                $value = $arguments[$index + 1] ?? null;

                return is_string($value) ? $value : null;
            }
        }

        return null;
    }
}
