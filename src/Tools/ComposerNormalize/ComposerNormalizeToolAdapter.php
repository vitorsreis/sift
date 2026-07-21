<?php

declare(strict_types=1);

namespace Sift\Tools\ComposerNormalize;

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\MutationPolicy;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class ComposerNormalizeToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private ComposerNormalizeParser $parser = new ComposerNormalizeParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'composer-normalize';
    }

    #[\Override]
    protected function aliases(): array
    {
        return ['normalize'];
    }

    protected function description(): string
    {
        return 'Composer manifest normalizer.';
    }

    protected function binaryCandidates(): array
    {
        return ['composer.cmd', 'composer.bat', 'composer'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev ergebnis/composer-normalize';
    }

    protected function defaultContext(): string
    {
        return 'style';
    }

    #[\Override]
    protected function versionCommand(): array
    {
        return ['show', 'ergebnis/composer-normalize', '--format=json'];
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

        $userArguments = $this->withoutArguments($context->userArgs(), [
            '--no-progress',
            '--ansi',
            '--no-ansi',
            '-n',
            '--no-interaction',
        ]);
        $arguments = ['normalize', '--no-ansi', '--no-interaction'];

        if (! $context->repair() && ! $this->hasOption($userArguments, '--dry-run')) {
            $arguments[] = '--dry-run';
        }

        if (! $this->hasOption($userArguments, '--diff')) {
            $arguments[] = '--diff';
        }

        return $this->prepareBaseCommand(
            tool: $tool,
            context: $context,
            config: $config,
            arguments: [...$arguments, ...$userArguments],
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
