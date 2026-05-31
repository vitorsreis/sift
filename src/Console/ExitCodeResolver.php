<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Core\ErrorCode;
use Sift\Core\RunStatus;

final readonly class ExitCodeResolver
{
    public function resolve(RunStatus $status, ?ErrorCode $errorCode = null): ExitCode
    {
        if ($status === RunStatus::Passed) {
            return ExitCode::Success;
        }

        if ($status === RunStatus::Failed || $status === RunStatus::Changed) {
            return ExitCode::Findings;
        }

        if ($errorCode === ErrorCode::ProcessInterrupted) {
            return ExitCode::Interrupted;
        }

        if ($this->isUserError($errorCode)) {
            return ExitCode::UserError;
        }

        return ExitCode::OperationalError;
    }

    private function isUserError(?ErrorCode $errorCode): bool
    {
        return in_array($errorCode, [
            ErrorCode::InvalidUsage,
            ErrorCode::InvalidConfig,
            ErrorCode::ConfigSchemaUnsupported,
            ErrorCode::ToolDisabled,
            ErrorCode::BlockedArgument,
            ErrorCode::PolicyBlocked,
            ErrorCode::SkillSelectionRequired,
            ErrorCode::UnsupportedTarget,
        ], true);
    }
}
