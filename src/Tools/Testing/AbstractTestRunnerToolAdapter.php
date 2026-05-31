<?php

declare(strict_types=1);

namespace Sift\Tools\Testing;

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
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
            arguments: [...$this->defaultArguments($context), ...$context->userArgs()],
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        try {
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

    private function commandFactory(): TestRunnerCommandFactory
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
