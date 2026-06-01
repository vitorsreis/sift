<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Skills\Targets\InstructionTargetRegistry;

it('resolves generic target and rejects unsupported targets', function (): void {
    $registry = new InstructionTargetRegistry();

    expect($registry->resolve('generic')->name())->toBe('generic');

    try {
        $registry->resolve('unknown-agent');
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::UnsupportedTarget);

        return;
    }

    throw new RuntimeException('Expected unsupported target exception.');
});
