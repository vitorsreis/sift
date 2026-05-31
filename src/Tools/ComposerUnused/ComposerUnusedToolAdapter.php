<?php

declare(strict_types=1);

namespace Sift\Tools\ComposerUnused;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class ComposerUnusedToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private ComposerUnusedParser $parser = new ComposerUnusedParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'composer-unused';
    }

    protected function description(): string
    {
        return 'Composer unused dependency analyzer.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/composer-unused.bat', 'vendor/bin/composer-unused', 'composer-unused'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev icanhazstring/composer-unused';
    }

    protected function defaultContext(): string
    {
        return 'dependency';
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
        $report = $this->parser->parse($execution->stdout(), $execution->stderr());

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->statusDecider->decide($execution, findings: $report->findings()),
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
        if ($this->hasOption($arguments, '--output-file')) {
            throw new InvalidUsageException('Composer Unused adapter requires JSON on stdout.');
        }

        $defaults = [];

        if (! $this->hasOption($arguments, '--output-format') && ! in_array('-o', $arguments, true)) {
            $defaults[] = '--output-format=json';
        }

        if (! in_array('--no-progress', $arguments, true)) {
            $defaults[] = '--no-progress';
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
}
