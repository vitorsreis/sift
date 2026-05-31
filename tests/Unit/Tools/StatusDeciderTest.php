<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\RunStatus;
use Sift\Tools\StatusDecider;

it('marks successful runs without findings as passed', function (): void {
    $status = (new StatusDecider())->decide(
        execution: ExecutionResult::completed(0, '', '', 0.1),
    );

    expect($status)->toBe(RunStatus::Passed);
});

it('marks expected findings as failed', function (): void {
    $status = (new StatusDecider())->decide(
        execution: ExecutionResult::completed(1, '', '', 0.1),
        findings: 2,
    );

    expect($status)->toBe(RunStatus::Failed);
});

it('marks applied changes as changed', function (): void {
    $status = (new StatusDecider())->decide(
        execution: ExecutionResult::completed(0, '', '', 0.1),
        changed: true,
    );

    expect($status)->toBe(RunStatus::Changed);
});

it('marks unexpected non-zero exits without findings as error', function (): void {
    $status = (new StatusDecider())->decide(
        execution: ExecutionResult::completed(2, '', 'fatal', 0.1),
    );

    expect($status)->toBe(RunStatus::Error);
});

it('marks parse failures and timeouts as errors', function (): void {
    $decider = new StatusDecider();

    expect($decider->decide(
        execution: ExecutionResult::completed(0, '', '', 0.1),
        errorCode: ErrorCode::ParseFailure,
    ))->toBe(RunStatus::Error);

    expect($decider->decide(
        execution: ExecutionResult::timeout('', 'timeout', 10.0),
    ))->toBe(RunStatus::Error);
});
