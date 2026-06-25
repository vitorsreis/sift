<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Skills\Targets\InstructionTargetRegistry;

it('resolves supported instruction targets and aliases', function (): void {
    $registry = new InstructionTargetRegistry();

    expect($registry->writeCapableNames())
        ->toContain('codex')
        ->toContain('cursor')
        ->toContain('windsurf')
        ->toContain('gemini-cli')
        ->toContain('github-copilot')
        ->toContain('opencode')
        ->toContain('antigravity')
        ->toContain('openclaw');

    expect($registry->resolve('generic')->name())->toBe('generic');
    expect($registry->resolve('claude')->name())->toBe('claude-code');
    expect($registry->resolve('gemini')->name())->toBe('gemini-cli');
    expect($registry->resolve('vscode')->name())->toBe('github-copilot');
});

it('reports unsupported targets', function (): void {
    $registry = new InstructionTargetRegistry();

    try {
        $registry->resolve('unknown-target');
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::UnsupportedTarget);
        expect($userFacingException->context())->toMatchArray(['recognized' => false]);

        return;
    }

    throw new RuntimeException('Expected unsupported target exception.');
});
