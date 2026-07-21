<?php

declare(strict_types=1);

namespace Sift\Tools\GrumPhp;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class GrumPhpToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private GrumPhpParser $parser = new GrumPhpParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'grumphp';
    }

    protected function description(): string
    {
        return 'GrumPHP code quality task runner.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/grumphp.bat', 'vendor/bin/grumphp', 'grumphp'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev phpro/grumphp';
    }

    protected function defaultContext(): string
    {
        return 'quality';
    }

    #[\Override]
    protected function versionCommand(): array
    {
        return ['--version'];
    }

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        if ($context->raw()) {
            return $this->prepareBaseCommand($tool, $context, $config, $context->userArgs());
        }

        $arguments = $context->userArgs();
        $first = $arguments[0] ?? null;

        if (is_string($first) && ! str_starts_with($first, '-')) {
            if ($first !== 'run') {
                throw new InvalidUsageException('GrumPHP adapter supports only the "run" command.');
            }

            $arguments = array_slice($arguments, 1);
        }

        $arguments = $this->withoutArguments($arguments, [
            '--ansi',
            '--no-ansi',
            '-n',
            '--no-interaction',
        ]);
        $defaults = ['run', '--no-ansi', '--no-interaction'];

        return $this->prepareBaseCommand(
            tool: $tool,
            context: $context,
            config: $config,
            arguments: [...$defaults, ...$arguments],
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        $report = $this->parser->parse($execution->stdout(), $execution->stderr());

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->statusDecider->decide($execution, findings: $report->failed()),
            summary: $report->summary(),
            items: $report->items(),
        );
    }
}
