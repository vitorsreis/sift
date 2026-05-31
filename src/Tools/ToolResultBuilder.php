<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Core\Clock;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Core\SystemClock;

final readonly class ToolResultBuilder
{
    public function __construct(
        private Clock $clock = new SystemClock(),
    ) {}

    public function build(
        NormalizedResult $parsed,
        ExecutionResult $execution,
        PreparedCommand $command,
        ToolContext $context,
    ): NormalizedResult {
        $meta = [
            'exit_code' => $execution->exitCode(),
            'duration' => $execution->durationSeconds(),
            'created_at' => $this->clock->now()->format(DATE_ATOM),
            'command' => $command->argv(),
            'filter' => $context->filter(),
            'coverage' => $context->coverage(),
            'coverage_min' => $context->coverageMin(),
            'mode' => $context->mode(),
            'dry_run' => $context->dryRun(),
            'subcommand' => $context->subcommand(),
            'warnings' => $context->warnings(),
        ];

        if ($context->repair()) {
            $meta['repair'] = true;
        }

        return $parsed->withMeta($meta);
    }
}
