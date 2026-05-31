<?php

declare(strict_types=1);

namespace Sift\Tools;

enum MutationPolicy: string
{
    case Never = 'never';
    case RepairFlag = 'repair_flag';
    case ExplicitDryRun = 'explicit_dry_run';
    case ToolSpecific = 'tool_specific';
}
