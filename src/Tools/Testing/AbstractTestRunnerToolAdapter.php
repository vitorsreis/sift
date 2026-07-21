<?php

declare(strict_types=1);

namespace Sift\Tools\Testing;

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\ItemType;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

abstract readonly class AbstractTestRunnerToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private ?TestRunnerCommandFactory $commandFactory = null,
        private JunitParser $junitParser = new JunitParser(),
        private CloverCoverageParser $cloverCoverageParser = new CloverCoverageParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        return $this->commandFactory()->prepare(
            toolName: $this->name(),
            tool: $tool,
            context: $context,
            config: $config,
            arguments: [...$this->defaultArguments($context), ...$this->userArguments($context)],
        );
    }

    /**
     * @return list<string>
     */
    protected function userArguments(ToolContext $context): array
    {
        return $context->userArgs();
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        try {
            if (! $execution->successful() && $this->missingGeneratedReport($command, $context)) {
                return $this->nativeExecutionError($execution);
            }

            $junitPath = $this->artifact($command, 'junit');
            $junit = $this->junitParser->parse($junitPath, $command->cwd(), $this->generatedFiles($command));
            $summary = $junit->summary();
            $items = $junit->items();
            $findings = $summary['failures'] + $summary['errors'];

            if ($context->coverage() || $context->coverageMin() !== null) {
                $clover = $this->cloverCoverageParser->parse(
                    path: $this->artifact($command, 'coverage_clover'),
                    cwd: $command->cwd(),
                    generatedFiles: $this->generatedFiles($command),
                    minimum: $context->coverageMin(),
                );
                $summary = [...$summary, ...$clover->summary()];
                $items = [...$items, ...$clover->items()];

                if ($clover->thresholdFailed()) {
                    ++$findings;
                }
            }

            return new NormalizedResult(
                tool: $this->name(),
                status: $this->statusDecider->decide($execution, findings: $findings),
                summary: $summary,
                items: $items,
            );
        } finally {
            $this->removeTemporaryFiles($command);
        }
    }

    private function nativeExecutionError(ExecutionResult $execution): NormalizedResult
    {
        return new NormalizedResult(
            tool: $this->name(),
            status: RunStatus::Error,
            summary: [
                'exit_code' => $execution->exitCode(),
            ],
            items: [[
                'type' => ItemType::Error->value,
                'message' => $this->nativeErrorMessage($execution),
            ]],
            extra: [
                'stdout' => trim($execution->stdout()),
                'stderr' => trim($execution->stderr()),
            ],
        );
    }

    private function nativeErrorMessage(ExecutionResult $execution): string
    {
        $output = trim($execution->stderr()) !== '' ? $execution->stderr() : $execution->stdout();
        $lines = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        foreach ($lines as $line) {
            if ($this->looksLikeErrorMessage($line)) {
                return $line;
            }
        }

        foreach ($lines as $line) {
            if (! $this->isRunnerBanner($line)) {
                return $line;
            }
        }

        if ($lines !== []) {
            return $lines[0];
        }

        return 'Test runner exited before writing reports.';
    }

    private function looksLikeErrorMessage(string $line): bool
    {
        return preg_match('/(?:^|\b)(?:Unknown option|No code coverage driver|Fatal error|Parse error|Error|Exception|Could not|Cannot|Failed)(?:\b|:|")/i', $line) === 1;
    }

    private function isRunnerBanner(string $line): bool
    {
        return preg_match('/^(?:PHPUnit|Pest|ParaTest|Codeception)\b/i', $line) === 1
            || preg_match('/^by Sebastian Bergmann and contributors\.?$/i', $line) === 1;
    }

    protected function commandFactory(): TestRunnerCommandFactory
    {
        return $this->commandFactory ?? new TestRunnerCommandFactory(new TempFileFactory(sys_get_temp_dir()));
    }

    private function artifact(PreparedCommand $command, string $name): string
    {
        $path = $command->artifacts()[$name] ?? null;

        if (! is_string($path) || $path === '') {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::ParseFailure,
                message: sprintf('Missing "%s" artifact for test runner.', $name),
            );
        }

        return $path;
    }

    private function missingGeneratedReport(PreparedCommand $command, ToolContext $context): bool
    {
        $artifacts = ['junit'];

        if ($context->coverage() || $context->coverageMin() !== null) {
            $artifacts[] = 'coverage_clover';
        }

        foreach ($artifacts as $artifact) {
            if ($this->reportMissingOrEmpty($command, $artifact)) {
                return true;
            }
        }

        return false;
    }

    private function reportMissingOrEmpty(PreparedCommand $command, string $name): bool
    {
        $path = $command->artifacts()[$name] ?? null;

        if (! is_string($path) || ! is_file($path)) {
            return true;
        }

        $size = filesize($path);

        return ! is_int($size) || $size === 0;
    }

    /**
     * @return list<string>
     */
    private function generatedFiles(PreparedCommand $command): array
    {
        return array_values($command->artifacts());
    }

    private function removeTemporaryFiles(PreparedCommand $command): void
    {
        foreach ($command->temporaryFiles() as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
