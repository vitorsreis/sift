<?php

declare(strict_types=1);

use Sift\Config\ConfigDefaults;

/**
 * @param list<string> $arguments
 *
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runSift(array $arguments): array
{
    $root = dirname(__DIR__, 2);
    $command = [PHP_BINARY, $root . '/bin/sift'];

    foreach ($arguments as $argument) {
        $command[] = $argument;
    }

    $process = proc_open(
        $command,
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $root,
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Could not start Sift process.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if ($stdout === false || $stderr === false) {
        throw new RuntimeException('Could not read Sift process output.');
    }

    return [
        'exit_code' => proc_close($process),
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/**
 * @return array{
 *     tool: string,
 *     status: string,
 *     summary: array<string, mixed>,
 *     items: array<int, mixed>,
 *     meta: array<string, mixed>
 * }
 */
function decodeSiftPayload(string $json): array
{
    $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($payload)) {
        throw new RuntimeException('Sift payload must be an object.');
    }

    return [
        'tool' => stringField($payload, 'tool'),
        'status' => stringField($payload, 'status'),
        'summary' => array_key_exists('summary', $payload) ? objectField($payload, 'summary') : [],
        'items' => array_key_exists('items', $payload) ? listField($payload, 'items') : [],
        'meta' => array_key_exists('meta', $payload) ? objectField($payload, 'meta') : [],
    ];
}

/**
 * @param array<mixed> $payload
 */
function stringField(array $payload, string $key): string
{
    $value = $payload[$key] ?? null;

    if (! is_string($value)) {
        throw new RuntimeException(sprintf('Expected string field "%s".', $key));
    }

    return $value;
}

/**
 * @param array<mixed> $payload
 *
 * @return array<string, mixed>
 */
function objectField(array $payload, string $key): array
{
    $value = $payload[$key] ?? null;

    if (! is_array($value) || array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected object field "%s".', $key));
    }

    $normalized = [];

    foreach ($value as $field => $fieldValue) {
        if (! is_string($field)) {
            throw new RuntimeException(sprintf('Expected string keys in "%s".', $key));
        }

        $normalized[$field] = $fieldValue;
    }

    return $normalized;
}

/**
 * @param array<mixed> $payload
 *
 * @return array<int, mixed>
 */
function listField(array $payload, string $key): array
{
    $value = $payload[$key] ?? null;

    if (! is_array($value) || ! array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected list field "%s".', $key));
    }

    return $value;
}

it('renders help as terminal text by default', function (): void {
    $result = runSift(['--compact', 'help']);
    $stdout = stripSiftAnsi($result['stdout']);

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($stdout)->toContain('Sift');
    expect($stdout)->toContain('Usage');
    expect($stdout)->toContain('Commands');
    expect($stdout)->toContain('Options');
    expect($stdout)->toContain('composer sift [options] <command>');
    expect($stdout)->toContain('tools list');
    expect($stdout)->toContain('--json');
    expect($stdout)->toContain('--no-json');
    expect($stdout)->toContain('Terminal-only commands');
    expect($stdout)->toContain('help, version, tools list');
    expect($stdout)->toContain('--compact');
});

it('renders help as terminal text even when json is requested', function (): void {
    $result = runSift(['--json', 'help']);
    $stdout = stripSiftAnsi($result['stdout']);

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($stdout)->toContain('Sift');
    expect($stdout)->toContain('<tool> [args]');
    expect($stdout)->toContain('run <tool> [args]');
});

it('renders version as terminal text by default', function (): void {
    $result = runSift(['version']);

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect(stripSiftAnsi($result['stdout']))->toStartWith('Sift ');
});

it('renders version as terminal text even when json is requested', function (): void {
    $result = runSift(['--json', 'version']);

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect(stripSiftAnsi($result['stdout']))->toStartWith('Sift ');
});

it('renders tools list as terminal text by default', function (): void {
    $root = dirname(__DIR__, 2);
    $config = $root . '/.sift-test-terminal.json';
    file_put_contents($config, json_encode([
        '$schema' => ConfigDefaults::schemaUrl(),
        'output' => [
            'format' => 'terminal',
            'size' => 'compact',
            'pretty' => false,
            'show_process' => false,
        ],
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    try {
        $result = runSift(['--config=' . $config, '--compact', 'tools', 'list']);
    } finally {
        @unlink($config);
    }

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($result['stdout'])->toContain('Tools');
    expect($result['stdout'])->toContain('Supported tools and local availability.');
    expect($result['stdout'])->toContain('Pest');
    expect($result['stdout'])->toContain('PHPUnit');
});

it('keeps help terminal text when full json is requested', function (): void {
    $result = runSift(['--json', '--full', 'help']);

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($result['stdout'])->toContain('Commands');
});

it('renders tools list from the cli as terminal text even when json is requested', function (): void {
    $result = runSift(['--json', '--full', '--no-pretty', 'tools', 'list']);

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($result['stdout'])->toContain('Tools');
    expect($result['stdout'])->toContain('Supported tools and local availability.');
    expect($result['stdout'])->toContain('OK');
    expect($result['stdout'])->not->toStartWith('{');
});

it('forces terminal output with no-json even when config requests json', function (): void {
    $result = runSift(['--no-json', 'validate']);

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect(stripSiftAnsi($result['stdout']))->toContain('sift passed');
    expect(stripSiftAnsi($result['stdout']))->not->toStartWith('{');
});

it('renders invalid usage errors as JSON on stderr', function (): void {
    $result = runSift(['--json', 'tools', 'add']);
    $payload = json_decode($result['stderr'], true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($payload)) {
        throw new RuntimeException('Sift error payload must be an object.');
    }

    $error = $payload['error'] ?? null;

    if (! is_array($error)) {
        throw new RuntimeException('Sift error payload must include an error object.');
    }

    expect($result['exit_code'])->toBe(3);
    expect($result['stdout'])->toBe('');
    expect($payload['status'] ?? null)->toBe('error');
    expect($error['code'] ?? null)->toBe('invalid_usage');
    expect($error['message'] ?? null)->toBe('Unknown command "tools add".');
});

it('respects pretty and no-pretty output flags', function (): void {
    $pretty = runSift(['--json', '--pretty', 'validate']);
    $compact = runSift(['--json', '--no-pretty', 'validate']);

    expect($pretty['exit_code'])->toBe(0);
    expect($compact['exit_code'])->toBe(0);
    expect($pretty['stdout'])->toContain("\n" . '    "tool": "sift"');
    expect($compact['stdout'])->not->toContain("\n    ");
    expect(substr_count($compact['stdout'], "\n"))->toBe(1);
});

it('writes debug diagnostics to stderr without changing stdout', function (): void {
    $token = 'ghp_abcdefghijklmnopqrstuvwxyz123456';
    $normal = runSift(['--no-pretty', '--config=' . $token, 'help']);
    $debug = runSift(['--debug', '--no-pretty', '--config=' . $token, 'help']);
    $diagnostic = json_decode($debug['stderr'], true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($diagnostic)) {
        throw new RuntimeException('Debug payload must be an object.');
    }

    $globalOptions = $diagnostic['global_options'] ?? null;

    if (! is_array($globalOptions)) {
        throw new RuntimeException('Debug payload must include global_options.');
    }

    expect($debug['exit_code'])->toBe(0);
    expect($debug['stdout'])->toBe($normal['stdout']);
    expect($debug['stderr'])->not->toContain($token);
    expect($diagnostic['type'] ?? null)->toBe('debug');
    expect($diagnostic['handler'] ?? null)->toBe('help');
    expect($globalOptions['config'] ?? null)->toBe('[REDACTED]');
});
