<?php

declare(strict_types=1);

namespace Sift\Tools\PhpStan;

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class PhpstanToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private PhpstanParser $parser = new PhpstanParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'phpstan';
    }

    protected function description(): string
    {
        return 'PHPStan static analyser.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/phpstan.bat', 'vendor/bin/phpstan', 'phpstan'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev phpstan/phpstan';
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
            arguments: $this->arguments($context->userArgs()),
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
        $remaining = $userArguments;
        $first = $remaining[0] ?? null;

        if ($first === 'analyse' || $first === 'analyze') {
            $arguments[] = $first;
            $remaining = array_slice($remaining, 1);
        } else {
            $arguments[] = 'analyse';
        }

        if (! $this->hasOption($remaining, '--error-format')) {
            $arguments[] = '--error-format=json';
        }

        if (! in_array('--no-progress', $remaining, true)) {
            $arguments[] = '--no-progress';
        }

        return [...$arguments, ...$remaining];
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
