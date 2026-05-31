<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Safety\PolicyViolation;

it('serializes policy violations for error payloads', function (): void {
    $violation = new PolicyViolation(
        code: ErrorCode::BlockedArgument,
        message: 'Argument blocked.',
        policy: 'blocked_arguments',
        argument: '--watch',
    );

    expect($violation->code())->toBe(ErrorCode::BlockedArgument);
    expect($violation->message())->toBe('Argument blocked.');
    expect($violation->policy())->toBe('blocked_arguments');
    expect($violation->argument())->toBe('--watch');
    expect($violation->toPayload())->toBe([
        'code' => 'blocked_argument',
        'message' => 'Argument blocked.',
        'argument' => '--watch',
        'policy' => 'blocked_arguments',
    ]);
});
