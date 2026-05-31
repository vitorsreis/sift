<?php

declare(strict_types=1);

namespace Sift\Tools;

use InvalidArgumentException;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\RunStatus;

final readonly class StatusDecider
{
    public function decide(
        ExecutionResult $execution,
        int $findings = 0,
        bool $changed = false,
        ?ErrorCode $errorCode = null,
    ): RunStatus {
        if ($findings < 0) {
            throw new InvalidArgumentException('Findings count cannot be negative.');
        }

        if ($errorCode instanceof ErrorCode || $execution->timedOut() || $execution->interrupted()) {
            return RunStatus::Error;
        }

        if ($changed) {
            return RunStatus::Changed;
        }

        if ($findings > 0) {
            return RunStatus::Failed;
        }

        if (! $execution->successful()) {
            return RunStatus::Error;
        }

        return RunStatus::Passed;
    }
}
