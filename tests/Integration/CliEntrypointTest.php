<?php

declare(strict_types=1);

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

it('renders help as a normalized JSON payload', function (): void {
    $result = runSift(['help']);
    $payload = decodeSiftPayload($result['stdout']);
    $rawPayload = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($rawPayload)) {
        throw new RuntimeException('Sift payload must be an object.');
    }

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($payload['tool'])->toBe('sift');
    expect($payload['status'])->toBe('passed');
    expect($rawPayload['command'] ?? null)->toBe('help');
    expect(array_key_exists('summary', $rawPayload))->toBeFalse();
    expect($payload['summary'])->toBe([]);
    expect($payload['items'])->toBe([]);
    expect($payload['meta'])->toBe([]);
});

it('renders full output when requested', function (): void {
    $result = runSift(['--full', 'help']);
    $payload = decodeSiftPayload($result['stdout']);

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($payload['tool'])->toBe('sift');
    expect($payload['status'])->toBe('passed');
    expect($payload['summary']['command'] ?? null)->toBe('help');
    expect($payload['items'])->toBeArray();
    expect($payload['meta']['subcommand'] ?? null)->toBe('help');
});

it('renders invalid usage errors as JSON on stderr', function (): void {
    $result = runSift(['tools', 'add']);
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
    $pretty = runSift(['--pretty', 'help']);
    $compact = runSift(['--no-pretty', 'help']);

    expect($pretty['exit_code'])->toBe(0);
    expect($compact['exit_code'])->toBe(0);
    expect($pretty['stdout'])->toContain("\n" . '    "tool": "sift"');
    expect($compact['stdout'])->not->toContain("\n    ");
    expect(substr_count($compact['stdout'], "\n"))->toBe(1);
});
