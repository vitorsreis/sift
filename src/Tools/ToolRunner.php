<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Config\SiftConfig;
use Sift\Config\ToolConfigResolver;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\PhpCommandFactory;
use Sift\Execution\ProcessRunner;
use Sift\Execution\RawProcessRunner;
use Sift\Execution\ToolResolver;
use Sift\Registry\ToolRegistryInterface;
use Sift\Safety\PolicyPipeline;

final readonly class ToolRunner
{
    public function __construct(
        private ToolRegistryInterface $registry,
        private ToolConfigResolver $configResolver = new ToolConfigResolver(),
        private ToolResolver $toolResolver = new ToolResolver(),
        private PolicyPipeline $policyPipeline = new PolicyPipeline([]),
        private ProcessRunner $processRunner = new ProcessRunner(),
        private RawProcessRunner $rawProcessRunner = new RawProcessRunner(),
        private ToolResultBuilder $resultBuilder = new ToolResultBuilder(),
        private PhpCommandFactory $phpCommandFactory = new PhpCommandFactory(),
    ) {}

    public function run(
        CliArguments $arguments,
        SiftConfig $config,
        string $cwd,
        mixed $rawStdout = null,
        mixed $rawStderr = null,
        ?callable $processReporter = null,
    ): NormalizedResult|ExecutionResult {
        $adapter = $this->registry->find($arguments->tool());

        if (! $adapter instanceof ToolAdapter) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::InvalidUsage,
                message: sprintf('Unknown tool "%s".', $arguments->tool()),
                context: ['tool' => $arguments->tool()],
            );
        }

        $definition = $adapter->definition();
        $toolConfig = $this->configResolver->resolve($config, $definition->name());
        $context = $adapter->context($arguments, $cwd);
        $locatedTool = $this->toolResolver->resolve($definition, $toolConfig, $cwd);
        $command = $adapter->prepare($locatedTool, $context, $toolConfig);

        $this->policyPipeline->assertAllowed($command, $context, $toolConfig);
        $command = $this->phpCommandFactory->apply($command, $this->phpArguments($arguments));
        $this->reportProcess($command, $processReporter);

        if ($context->raw()) {
            return $this->rawProcessRunner->run($command, $rawStdout, $rawStderr);
        }

        $execution = $this->processRunner->run($command);
        $parsed = $adapter->parse($execution, $context, $command);

        return $this->resultBuilder->build($parsed, $execution, $command, $context);
    }

    /**
     * @return list<string>
     */
    private function phpArguments(CliArguments $arguments): array
    {
        $phpArgument = $arguments->siftOption('d');

        if ($phpArgument === null) {
            return [];
        }

        if (is_string($phpArgument)) {
            return [$this->phpDefineArgument($phpArgument)];
        }

        if (! is_array($phpArgument)) {
            return [];
        }

        $phpArgument = array_values(array_filter(
            $phpArgument,
            static fn(mixed $argument): bool => is_string($argument) && $argument !== '',
        ));

        return array_map($this->phpDefineArgument(...), $phpArgument);
    }

    private function phpDefineArgument(string $argument): string
    {
        return '-d' . $argument;
    }

    /**
     * @param (callable(PreparedCommand): void)|null $processReporter
     */
    private function reportProcess(PreparedCommand $command, ?callable $processReporter): void
    {
        if ($processReporter === null) {
            return;
        }

        $processReporter($command);
    }
}
