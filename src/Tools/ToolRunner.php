<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Config\SiftConfig;
use Sift\Config\ToolConfigResolver;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Exceptions\UserFacingException;
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
    ) {}

    public function run(
        CliArguments $arguments,
        SiftConfig $config,
        string $cwd,
        mixed $rawStdout = null,
        mixed $rawStderr = null,
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

        if ($context->raw()) {
            return $this->rawProcessRunner->run($command, $rawStdout, $rawStderr);
        }

        $execution = $this->processRunner->run($command);
        $parsed = $adapter->parse($execution, $context);

        return $this->resultBuilder->build($parsed, $execution, $command, $context);
    }
}
