<?php

declare(strict_types=1);

namespace Sift\Tools\Deptrac;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\CliArguments;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class DeptracToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private DeptracParser $parser = new DeptracParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'deptrac';
    }

    protected function description(): string
    {
        return 'Deptrac architecture dependency analyser.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/deptrac.bat', 'vendor/bin/deptrac', 'deptrac'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev deptrac/deptrac';
    }

    protected function defaultContext(): string
    {
        return 'architecture';
    }

    #[\Override]
    protected function versionCommand(): array
    {
        return ['--version'];
    }

    #[\Override]
    public function context(CliArguments $arguments, string $cwd): ToolContext
    {
        return new ToolContext(
            toolName: $this->name(),
            subcommand: 'analyse',
            userArgs: $arguments->toolArguments(),
            cwd: $cwd,
            raw: $arguments->siftOption('raw') === true,
            debug: $arguments->siftOption('debug') === true,
            mode: 'architecture',
        );
    }

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        $arguments = $this->withoutAnalysePseudoSubcommand($context->userArgs());

        if ($context->raw()) {
            return $this->prepareBaseCommand($tool, $context, $config, $arguments);
        }

        $this->assertStdoutJson($arguments);

        return $this->prepareBaseCommand(
            tool: $tool,
            context: $context,
            config: $config,
            arguments: $this->safeArguments($arguments),
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        if ($this->jsonFormatterUnsupported($execution)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::UnsupportedToolVersion,
                message: 'Deptrac JSON output is not supported by the installed version.',
                hint: 'Upgrade deptrac/deptrac to a version that provides the json formatter.',
                context: ['tool' => $this->name()],
            );
        }

        $report = $this->parser->parse($execution->stdout(), $execution->stderr(), $context->cwd());

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->statusDecider->decide(
                execution: $execution,
                findings: $report->violations(),
                errorCode: $report->errors() > 0 ? ErrorCode::ProcessFailed : null,
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
    private function withoutAnalysePseudoSubcommand(array $arguments): array
    {
        $first = $arguments[0] ?? null;

        if ($first === null || str_starts_with($first, '-')) {
            return $arguments;
        }

        if (in_array($first, ['analyse', 'analyze'], true)) {
            return array_slice($arguments, 1);
        }

        throw new InvalidUsageException('Deptrac adapter supports only the "analyse" command.');
    }

    /**
     * @param list<string> $arguments
     */
    private function assertStdoutJson(array $arguments): void
    {
        if ($this->hasOption($arguments, '--output') || $this->hasOption($arguments, '-o')) {
            throw new InvalidUsageException('Deptrac adapter requires JSON on stdout.');
        }
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function safeArguments(array $arguments): array
    {
        $defaults = [];
        $arguments = $this->withoutArguments($arguments, [
            '--no-progress',
            '--ansi',
            '--no-ansi',
            '-n',
            '--no-interaction',
            '-q',
            '--quiet',
        ]);

        if (! $this->hasOption($arguments, '--formatter') && ! $this->hasOption($arguments, '-f')) {
            $defaults[] = '--formatter=json';
        }

        $defaults[] = '--no-progress';
        $defaults[] = '--no-ansi';
        $defaults[] = '--no-interaction';

        if (! $this->hasOption($arguments, '--report-skipped')) {
            $defaults[] = '--report-skipped';
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

    private function jsonFormatterUnsupported(ExecutionResult $execution): bool
    {
        $output = $execution->stdout() . "\n" . $execution->stderr();

        return preg_match('/Output formatter json not found|Invalid output formatter/i', $output) === 1;
    }
}
