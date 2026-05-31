<?php

declare(strict_types=1);

use Sift\Console\ExitCode;
use Sift\Console\ExitCodeResolver;
use Sift\Core\ErrorCode;
use Sift\Core\RunStatus;

it('maps normalized statuses to process exit codes', function (): void {
    $resolver = new ExitCodeResolver();

    expect($resolver->resolve(RunStatus::Passed))->toBe(ExitCode::Success);
    expect($resolver->resolve(RunStatus::Failed))->toBe(ExitCode::Findings);
    expect($resolver->resolve(RunStatus::Changed))->toBe(ExitCode::Findings);
});

it('maps user-facing errors to user error exit code', function (ErrorCode $errorCode): void {
    expect((new ExitCodeResolver())->resolve(RunStatus::Error, $errorCode))->toBe(ExitCode::UserError);
})->with([
    ErrorCode::InvalidUsage,
    ErrorCode::InvalidConfig,
    ErrorCode::ConfigSchemaUnsupported,
    ErrorCode::ToolDisabled,
    ErrorCode::BlockedArgument,
    ErrorCode::PolicyBlocked,
    ErrorCode::SkillSelectionRequired,
    ErrorCode::UnsupportedTarget,
]);

it('maps operational errors to operational exit code', function (ErrorCode $errorCode): void {
    expect((new ExitCodeResolver())->resolve(RunStatus::Error, $errorCode))->toBe(ExitCode::OperationalError);
})->with([
    ErrorCode::ToolNotFound,
    ErrorCode::ProcessFailed,
    ErrorCode::ProcessTimeout,
    ErrorCode::ParseFailure,
    ErrorCode::FilesystemError,
    ErrorCode::HistoryWriteFailed,
]);

it('maps interruptions to interrupted exit code', function (): void {
    expect((new ExitCodeResolver())->resolve(RunStatus::Error, ErrorCode::ProcessInterrupted))->toBe(ExitCode::Interrupted);
});
