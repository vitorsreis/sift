<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Skills\Targets\GeminiInstructionTarget;
use Sift\Skills\Targets\InstructionTargetRegistry;

it('resolves generic target and rejects unsupported targets', function (): void {
    $registry = new InstructionTargetRegistry();

    expect($registry->resolve('codex')->name())->toBe('codex');
    expect($registry->resolve('cursor')->name())->toBe('cursor');
    expect($registry->resolve('windsurf')->name())->toBe('windsurf');
    expect($registry->resolve('gemini'))->toBeInstanceOf(GeminiInstructionTarget::class);
    expect($registry->resolve('generic')->name())->toBe('generic');
    expect($registry->resolve('claude')->name())->toBe('claude-code');
    expect($registry->resolve('copilot')->name())->toBe('github-copilot');
    expect($registry->resolve('vscode')->name())->toBe('github-copilot');
    expect($registry->resolve('vs-code')->name())->toBe('github-copilot');
    expect($registry->resolve('visual-studio-code')->name())->toBe('github-copilot');
    expect($registry->writeCapableNames())->toBe(['codex', 'cursor', 'windsurf', 'gemini', 'generic', 'claude-code', 'github-copilot']);

    foreach (['opencode', 'antigravity'] as $recognizedButNotWritable) {
        try {
            $registry->resolve($recognizedButNotWritable);
        } catch (UserFacingException $userFacingException) {
            expect($userFacingException->errorCode())->toBe(ErrorCode::UnsupportedTarget);
            expect($userFacingException->context()['recognized'] ?? null)->toBeTrue();

            continue;
        }

        throw new RuntimeException('Expected recognized target without write support to fail.');
    }

    try {
        $registry->resolve('unknown-agent');
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::UnsupportedTarget);
        expect($userFacingException->context()['recognized'] ?? null)->toBeFalse();

        return;
    }

    throw new RuntimeException('Expected unsupported target exception.');
});
