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

it('redacts short secrets in flags urls query strings and paths', function (): void {
    $redactor = new SecretRedactor();

    expect($redactor->redact([
        'flag' => '--password=short',
        'separate_flag' => '--api-token tiny',
        'url_credentials' => 'https://user:pass@example.com/path',
        'query' => 'https://example.com/file.php?api_key=abc&safe=yes',
        'path' => '/tmp/token=abc/file.php',
    ]))->toBe([
        'flag' => '--password=[REDACTED]',
        'separate_flag' => '--api-token [REDACTED]',
        'url_credentials' => 'https://user:[REDACTED]@example.com/path',
        'query' => 'https://example.com/file.php?api_key=[REDACTED]&safe=yes',
        'path' => '/tmp/token=[REDACTED]/file.php',
    ]);
});
