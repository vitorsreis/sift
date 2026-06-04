<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Skills\Targets\InstructionFileTarget;
use Sift\Skills\Targets\InstructionTargetRegistry;

it('resolves supported instruction targets and aliases', function (): void {
    $registry = new InstructionTargetRegistry();

    expect($registry->writeCapableNames())->toBe([
        'codex',
        'cursor',
        'windsurf',
        'gemini',
        'generic',
        'claude-code',
        'github-copilot',
    ]);

    expect($registry->resolve('generic'))->toBeInstanceOf(InstructionFileTarget::class);
    expect($registry->resolve('claude'))->toBeInstanceOf(InstructionFileTarget::class);
    expect($registry->resolve('vscode'))->toBeInstanceOf(InstructionFileTarget::class);
});

it('reports recognized readonly and unsupported targets', function (): void {
    $registry = new InstructionTargetRegistry();

    try {
        $registry->resolve('opencode');
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::UnsupportedTarget);
        expect($userFacingException->context())->toMatchArray(['recognized' => true]);
    }

    try {
        $registry->resolve('unknown-target');
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::UnsupportedTarget);
        expect($userFacingException->context())->toMatchArray(['recognized' => false]);

        return;
    }

    throw new RuntimeException('Expected unsupported target exception.');
});
