<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Execution\ProcessRunner;

it('runs a prepared command and captures native output', function (): void {
    $result = (new ProcessRunner())->run(new PreparedCommand(
        tool: 'php',
        binary: PHP_BINARY,
        arguments: ['-r', 'fwrite(STDOUT, "out"); fwrite(STDERR, "err"); exit(7);'],
        cwd: getcwd() ?: '.',
    ));

    expect($result->exitCode())->toBe(7);
    expect($result->stdout())->toBe('out');
    expect($result->stderr())->toBe('err');
    expect($result->timedOut())->toBeFalse();
});

it('applies prepared command timeout', function (): void {
    $result = (new ProcessRunner())->run(new PreparedCommand(
        tool: 'php',
        binary: PHP_BINARY,
        arguments: ['-r', 'sleep(3);'],
        cwd: getcwd() ?: '.',
        timeout: 1,
    ));

    expect($result->timedOut())->toBeTrue();
    expect($result->exitCode())->toBe(2);
    expect($result->errorCode())->toBe(ErrorCode::ProcessTimeout);
});

it('treats timeout zero as no timeout', function (): void {
    $result = (new ProcessRunner())->run(new PreparedCommand(
        tool: 'php',
        binary: PHP_BINARY,
        arguments: ['-r', 'usleep(100000); fwrite(STDOUT, "done");'],
        cwd: getcwd() ?: '.',
        timeout: 0,
    ));

    expect($result->timedOut())->toBeFalse();
    expect($result->stdout())->toBe('done');
});

it('removes prepared temporary files after successful execution', function (): void {
    $temporary = tempnam(sys_get_temp_dir(), 'sift-runner-success-');

    if ($temporary === false) {
        throw new RuntimeException('Could not create temporary file.');
    }

    file_put_contents($temporary, 'temporary report');

    $result = (new ProcessRunner())->run(new PreparedCommand(
        tool: 'php',
        binary: PHP_BINARY,
        arguments: ['-r', 'exit(0);'],
        cwd: getcwd() ?: '.',
        temporaryFiles: [$temporary],
    ));

    expect($result->exitCode())->toBe(0);
    expect($temporary)->not->toBeFile();
});

it('removes prepared temporary files after timeout', function (): void {
    $temporary = tempnam(sys_get_temp_dir(), 'sift-runner-timeout-');

    if ($temporary === false) {
        throw new RuntimeException('Could not create temporary file.');
    }

    file_put_contents($temporary, 'temporary report');

    $result = (new ProcessRunner())->run(new PreparedCommand(
        tool: 'php',
        binary: PHP_BINARY,
        arguments: ['-r', 'sleep(3);'],
        cwd: getcwd() ?: '.',
        timeout: 1,
        temporaryFiles: [$temporary],
    ));

    expect($result->timedOut())->toBeTrue();
    expect($temporary)->not->toBeFile();
});
