<?php

declare(strict_types=1);

use Sift\Console\ExitCode;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;

it('describes a completed process execution', function (): void {
    $result = ExecutionResult::completed(
        exitCode: 1,
        stdout: "stdout\n",
        stderr: "stderr\n",
        durationSeconds: 0.25,
    );

    expect($result->exitCode())->toBe(1);
    expect($result->stdout())->toBe("stdout\n");
    expect($result->stderr())->toBe("stderr\n");
    expect($result->durationSeconds())->toBe(0.25);
    expect($result->successful())->toBeFalse();
    expect($result->timedOut())->toBeFalse();
    expect($result->interrupted())->toBeFalse();
    expect($result->errorCode())->toBeNull();
});

it('describes a timed out process execution', function (): void {
    $result = ExecutionResult::timeout(
        stdout: 'partial',
        stderr: 'timeout',
        durationSeconds: 10.0,
    );

    expect($result->exitCode())->toBe(ExitCode::OperationalError->value);
    expect($result->successful())->toBeFalse();
    expect($result->timedOut())->toBeTrue();
    expect($result->interrupted())->toBeFalse();
    expect($result->errorCode())->toBe(ErrorCode::ProcessTimeout);
});

it('describes an interrupted process execution', function (): void {
    $result = ExecutionResult::interruption(
        stdout: 'partial',
        stderr: '',
        durationSeconds: 1.5,
    );

    expect($result->exitCode())->toBe(ExitCode::Interrupted->value);
    expect($result->timedOut())->toBeFalse();
    expect($result->interrupted())->toBeTrue();
    expect($result->errorCode())->toBe(ErrorCode::ProcessInterrupted);
});
