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
 *     summary: array{command: string},
 *     items: array<int, mixed>,
 *     meta: array{subcommand: string}
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
        'summary' => [
            'command' => stringField(arrayField($payload, 'summary'), 'command'),
        ],
        'items' => listField($payload, 'items'),
        'meta' => [
            'subcommand' => stringField(arrayField($payload, 'meta'), 'subcommand'),
        ],
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
 * @return array<mixed>
 */
function arrayField(array $payload, string $key): array
{
    $value = $payload[$key] ?? null;

    if (! is_array($value)) {
        throw new RuntimeException(sprintf('Expected array field "%s".', $key));
    }

    return $value;
}

/**
 * @param array<mixed> $payload
 *
 * @return array<int, mixed>
 */
function listField(array $payload, string $key): array
{
    $value = arrayField($payload, $key);

    if (! array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected list field "%s".', $key));
    }

    return $value;
}

it('renders help as a normalized JSON payload', function (): void {
    $result = runSift(['help']);
    $payload = decodeSiftPayload($result['stdout']);

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($payload['tool'])->toBe('sift');
    expect($payload['status'])->toBe('passed');
    expect($payload['summary']['command'])->toBe('help');
    expect($payload['items'])->toBeArray();
    expect($payload['meta']['subcommand'])->toBe('help');
});
