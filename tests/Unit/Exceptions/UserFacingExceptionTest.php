<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\SiftException;
use Sift\Exceptions\UserFacingException;

it('carries a user-facing error code, hint, and structured context', function (): void {
    $exception = UserFacingException::withContext(
        errorCode: ErrorCode::PolicyBlocked,
        message: 'Execution blocked by policy.',
        hint: 'Remove the blocked option.',
        context: ['argument' => '--watch'],
    );

    expect($exception)->toBeInstanceOf(SiftException::class);
    expect($exception->errorCode())->toBe(ErrorCode::PolicyBlocked);
    expect($exception->hint())->toBe('Remove the blocked option.');
    expect($exception->context())->toBe(['argument' => '--watch']);
    expect($exception->getMessage())->toBe('Execution blocked by policy.');
});
