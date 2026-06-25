<?php

declare(strict_types=1);

namespace Sift\Tools\PhpCs;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\MutationPolicy;
use Sift\Tools\ToolContext;

final readonly class PhpcbfToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private PhpcbfParser $parser = new PhpcbfParser(),
    ) {}

    protected function name(): string
    {
        return 'phpcbf';
    }

    protected function description(): string
    {
        return 'PHP_CodeSniffer code fixer.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/phpcbf.bat', 'vendor/bin/phpcbf', 'phpcbf'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev squizlabs/php_codesniffer';
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
        if (! $context->repair()) {
            throw new InvalidUsageException('PHPCBF modifies files; pass --repair to run it.');
        }

        return $this->prepareBaseCommand(
            tool: $tool,
            context: $context,
            config: $config,
            arguments: $this->arguments($context->userArgs()),
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        $report = $this->parser->parse($execution->stdout(), $execution->stderr(), $context->cwd());

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->status($execution, $report),
            summary: $report->summary(),
            items: $report->items(),
        );
    }

    /**
     * @param list<string> $userArguments
     * @return list<string>
     */
    private function arguments(array $userArguments): array
    {
        $arguments = [];

        if (! in_array('-q', $userArguments, true) && ! in_array('--quiet', $userArguments, true)) {
            $arguments[] = '-q';
        }

        if (! in_array('--no-colors', $userArguments, true) && ! in_array('--no-colours', $userArguments, true)) {
            $arguments[] = '--no-colors';
        }

        if (! $this->hasOption($userArguments, '--report-width')) {
            $arguments[] = '--report-width=500';
        }

        return [...$arguments, ...$userArguments];
    }

    private function status(ExecutionResult $execution, PhpcbfReport $report): RunStatus
    {
        if ($execution->timedOut() || $execution->interrupted()) {
            return RunStatus::Error;
        }

        if ($report->remaining() > 0) {
            return RunStatus::Failed;
        }

        if ($report->changed()) {
            return RunStatus::Changed;
        }

        if (! $report->recognized() && ! $execution->successful()) {
            return RunStatus::Error;
        }

        return $execution->successful() ? RunStatus::Passed : RunStatus::Error;
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
