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

it('renders help as a normalized JSON payload', function (): void {
    $result = runSift(['help']);
    $payload = json_decode((string) $result['stdout'], true, 512, JSON_THROW_ON_ERROR);

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($payload['tool'])->toBe('sift');
    expect($payload['status'])->toBe('passed');
    expect($payload['summary']['command'])->toBe('help');
    expect($payload['items'])->toBeArray();
    expect($payload['meta']['subcommand'])->toBe('help');
});
