<?php

declare(strict_types=1);

use Sift\History\SecretRedactor;

it('redacts sensitive keys recursively and deterministically', function (): void {
    $redactor = new SecretRedactor();
    $payload = [
        'token' => 'abc',
        'nested' => [
            'password' => 'secret',
            'api_key' => 'key',
            'safe' => 'value',
        ],
        'headers' => [
            'authorization' => 'Bearer abcdef123456',
            'cookie' => 'session=secret',
        ],
    ];

    expect($redactor->redact($payload))->toBe($redactor->redact($payload));
    expect($redactor->redact($payload))->toMatchArray([
        'token' => '[REDACTED]',
        'nested' => [
            'password' => '[REDACTED]',
            'api_key' => '[REDACTED]',
            'safe' => 'value',
        ],
        'headers' => [
            'authorization' => '[REDACTED]',
            'cookie' => '[REDACTED]',
        ],
    ]);
});

it('redacts bearer tokens github tokens and long secrets inside strings', function (): void {
    $redactor = new SecretRedactor();
    $payload = [
        'stdout' => 'Authorization: Bearer abcdef1234567890',
        'stderr' => 'token ghp_abcdefghijklmnopqrstuvwxyz123456 leaked',
        'extra' => 'secret ' . str_repeat('a', 48),
    ];

    expect($redactor->redact($payload))->toBe([
        'stdout' => 'Authorization: Bearer [REDACTED]',
        'stderr' => 'token [REDACTED] leaked',
        'extra' => 'secret [REDACTED]',
    ]);
});

it('does not redact file paths that look like long secret strings', function (): void {
    $redactor = new SecretRedactor();

    expect($redactor->redact([
        'file' => 'src/Console/Commands/SkillsUpdateCommand.php',
        'absolute' => 'D:\\Work\\projects\\others\\sift\\src\\Console\\Commands\\RunToolCommand.php',
    ]))->toBe([
        'file' => 'src/Console/Commands/SkillsUpdateCommand.php',
        'absolute' => 'D:\\Work\\projects\\others\\sift\\src\\Console\\Commands\\RunToolCommand.php',
    ]);
});
