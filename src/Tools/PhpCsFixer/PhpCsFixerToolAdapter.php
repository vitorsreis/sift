<?php

declare(strict_types=1);

namespace Sift\Tools\PhpCsFixer;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\CliArguments;
use Sift\Tools\MutationPolicy;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class PhpCsFixerToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private PhpCsFixerParser $parser = new PhpCsFixerParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'php-cs-fixer';
    }

    protected function description(): string
    {
        return 'PHP CS Fixer code style fixer.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/php-cs-fixer.bat', 'vendor/bin/php-cs-fixer', 'php-cs-fixer'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev friendsofphp/php-cs-fixer';
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
    public function context(CliArguments $arguments, string $cwd): ToolContext
    {
        $repair = in_array('--repair', $arguments->toolArguments(), true);

        return new ToolContext(
            toolName: $this->name(),
            subcommand: 'fix',
            userArgs: $arguments->toolArguments(),
            cwd: $cwd,
            raw: $arguments->siftOption('raw') === true,
            debug: $arguments->siftOption('debug') === true,
            repair: $repair,
            dryRun: ! $repair || $arguments->has('--dry-run'),
            mode: 'style',
        );
    }

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        $arguments = $this->withoutRepairFlag($context->userArgs());
        $arguments = $this->withoutFixCommand($arguments);

        return $this->prepareBaseCommand(
            tool: $tool,
            context: $context,
            config: $config,
            arguments: $context->raw() ? ['fix', ...$arguments] : $this->safeArguments($arguments, $context->repair()),
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        $report = $this->parser->parse($execution->stdout(), $execution->stderr(), $context->cwd());

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->statusDecider->decide(
                execution: $execution,
                findings: $context->repair() ? 0 : $report->files(),
                changed: $context->repair() && $report->files() > 0,
            ),
            summary: $report->summary(),
            items: $report->items(),
        );
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function withoutRepairFlag(array $arguments): array
    {
        return array_values(array_filter(
            $arguments,
            static fn(string $argument): bool => $argument !== '--repair',
        ));
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function withoutFixCommand(array $arguments): array
    {
        $first = $arguments[0] ?? null;

        if ($first === null || str_starts_with($first, '-')) {
            return $arguments;
        }

        if ($first === 'fix') {
            return array_slice($arguments, 1);
        }

        if (in_array($first, $this->unsupportedCommands(), true)) {
            throw new InvalidUsageException('PHP CS Fixer adapter supports only the "fix" command.');
        }

        return $arguments;
    }

    /**
     * @return list<string>
     */
    private function unsupportedCommands(): array
    {
        return [
            'check',
            'completion',
            'describe',
            'help',
            'list',
            'list-files',
            'readme',
            'self-update',
            'worker',
        ];
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function safeArguments(array $arguments, bool $repair): array
    {
        $defaults = ['fix'];

        if (! $repair && ! $this->hasOption($arguments, '--dry-run')) {
            $defaults[] = '--dry-run';
        }

        if (! $this->hasOption($arguments, '--format')) {
            $defaults[] = '--format=json';
        }

        if (! $this->hasOption($arguments, '--using-cache')) {
            $defaults[] = '--using-cache=no';
        }

        if (! $this->hasOption($arguments, '--diff')) {
            $defaults[] = '--diff';
        }

        if (! $this->hasVerbosity($arguments)) {
            $defaults[] = '-v';
        }

        return [...$defaults, ...$arguments];
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
    private function hasVerbosity(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if (in_array($argument, ['-v', '-vv', '-vvv', '--verbose'], true)) {
                return true;
            }
        }

        return false;
    }
}
