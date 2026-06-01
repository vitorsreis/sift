<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Skills\Targets\InstructionTargetRegistry;

it('resolves generic target and rejects unsupported targets', function (): void {
    $registry = new InstructionTargetRegistry();

    expect($registry->resolve('generic')->name())->toBe('generic');
    expect($registry->resolve('claude')->name())->toBe('claude-code');
    expect($registry->resolve('copilot')->name())->toBe('github-copilot');
    expect($registry->resolve('vscode')->name())->toBe('github-copilot');
    expect($registry->resolve('vs-code')->name())->toBe('github-copilot');
    expect($registry->resolve('visual-studio-code')->name())->toBe('github-copilot');
    expect($registry->writeCapableNames())->toBe(['generic', 'claude-code', 'github-copilot', 'gemini']);

    try {
        $registry->resolve('opencode');
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::UnsupportedTarget);

        return;
    }

    throw new RuntimeException('Expected unsupported target exception.');
});
