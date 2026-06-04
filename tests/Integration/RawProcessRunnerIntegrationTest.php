<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Execution\RawProcessRunner;

it('streams native stdout and stderr while preserving exit code', function (): void {
    $stdout = tmpfile();
    $stderr = tmpfile();

    if ($stdout === false || $stderr === false) {
        throw new RuntimeException('Could not create temporary streams.');
    }

    $result = (new RawProcessRunner())->run(
        command: new PreparedCommand(
            tool: 'php',
            binary: PHP_BINARY,
            arguments: ['-r', 'fwrite(STDOUT, "raw-out"); fwrite(STDERR, "raw-err"); exit(9);'],
            cwd: getcwd() ?: '.',
        ),
        stdout: $stdout,
        stderr: $stderr,
    );

    rewind($stdout);
    rewind($stderr);

    expect(stream_get_contents($stdout))->toBe('raw-out');
    expect(stream_get_contents($stderr))->toBe('raw-err');
    expect($result->exitCode())->toBe(9);
    expect($result->stdout())->toBe('');
    expect($result->stderr())->toBe('');
});

it('applies timeout while streaming raw output', function (): void {
    $stdout = tmpfile();
    $stderr = tmpfile();

    if ($stdout === false || $stderr === false) {
        throw new RuntimeException('Could not create temporary streams.');
    }

    $result = (new RawProcessRunner())->run(
        command: new PreparedCommand(
            tool: 'php',
            binary: PHP_BINARY,
            arguments: ['-r', 'fwrite(STDOUT, "started"); sleep(3);'],
            cwd: getcwd() ?: '.',
            timeout: 1,
        ),
        stdout: $stdout,
        stderr: $stderr,
    );

    rewind($stdout);

    expect(stream_get_contents($stdout))->toContain('started');
    expect($result->timedOut())->toBeTrue();
    expect($result->exitCode())->toBe(2);
    expect($result->errorCode())->toBe(ErrorCode::ProcessTimeout);
});

it('removes prepared temporary files after raw execution', function (): void {
    $stdout = tmpfile();
    $stderr = tmpfile();
    $temporary = tempnam(sys_get_temp_dir(), 'sift-raw-runner-');

    if ($stdout === false || $stderr === false || $temporary === false) {
        throw new RuntimeException('Could not create temporary resources.');
    }

    file_put_contents($temporary, 'temporary report');

    $result = (new RawProcessRunner())->run(
        command: new PreparedCommand(
            tool: 'php',
            binary: PHP_BINARY,
            arguments: ['-r', 'exit(0);'],
            cwd: getcwd() ?: '.',
            temporaryFiles: [$temporary],
        ),
        stdout: $stdout,
        stderr: $stderr,
    );

    expect($result->exitCode())->toBe(0);
    expect($temporary)->not->toBeFile();
});
