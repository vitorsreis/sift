<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;

interface ToolAdapter
{
    public function definition(): ToolDefinition;

    public function context(CliArguments $arguments, string $cwd): ToolContext;

    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand;

    public function parse(ExecutionResult $execution, ToolContext $context): NormalizedResult;
}
