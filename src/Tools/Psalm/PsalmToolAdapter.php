<?php

declare(strict_types=1);

namespace Sift\Tools\Psalm;

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class PsalmToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private PsalmParser $parser = new PsalmParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'psalm';
    }

    protected function description(): string
    {
        return 'Psalm static analyser.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/psalm.bat', 'vendor/bin/psalm', 'psalm'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev vimeo/psalm';
    }

    protected function defaultContext(): string
    {
        return 'analysis';
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
            arguments: $context->raw() ? $context->userArgs() : $this->arguments($context->userArgs()),
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        $report = $this->parser->parse($execution->stdout(), $execution->stderr(), $context->cwd());

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->statusDecider->decide($execution, findings: $report->findings()),
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
        $userArguments = $this->withoutArguments($userArguments, [
            '--no-progress',
            '--long-progress',
            '--monochrome',
        ]);

        if (! $this->hasOption($userArguments, '--output-format')) {
            $arguments[] = '--output-format=json';
        }

        $arguments[] = '--no-progress';
        $arguments[] = '--monochrome';

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
