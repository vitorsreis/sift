<?php

declare(strict_types=1);

namespace Sift\Tools\Mago;

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\ItemType;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\CliArguments;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class MagoToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private MagoArguments $arguments = new MagoArguments(),
        private MagoIssueParser $issueParser = new MagoIssueParser(),
        private MagoFormatParser $formatParser = new MagoFormatParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'mago';
    }

    protected function description(): string
    {
        return 'Mago PHP linter, analyzer, formatter and guard.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/mago.bat', 'vendor/bin/mago', 'mago'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev carthage-software/mago';
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
    public function context(CliArguments $arguments, string $cwd): ToolContext
    {
        $prepared = $this->arguments->prepare($arguments->toolArguments());

        return new ToolContext(
            toolName: $this->name(),
            subcommand: $prepared->subcommand(),
            userArgs: $arguments->toolArguments(),
            cwd: $cwd,
            raw: $arguments->siftOption('raw') === true,
            debug: $arguments->siftOption('debug') === true,
            dryRun: $arguments->has('--dry-run') || $arguments->has('-d') || $prepared->subcommand() === 'format',
            mode: $prepared->subcommand(),
        );
    }

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        return $this->prepareBaseCommand(
            tool: $tool,
            context: $context,
            config: $config,
            arguments: $this->arguments->prepare($context->userArgs(), ! $context->raw())->arguments(),
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        $subcommand = $this->arguments->subcommandFromPrepared($command->arguments());
        $report = $subcommand === 'format'
            ? $this->formatParser->parse($execution->stdout(), $execution->stderr(), $context->cwd())
            : $this->issueParser->parse(
                stdout: $execution->stdout(),
                stderr: $execution->stderr(),
                cwd: $context->cwd(),
                forcedItemType: $subcommand === 'guard' ? ItemType::ArchitectureViolation : null,
            );

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->statusDecider->decide($execution, findings: $report->findings()),
            summary: $report->summary(),
            items: $report->items(),
        );
    }
}
