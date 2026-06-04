<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Execution\ProcessSupervisor;

it('captures large stdout and stderr without pipe deadlock', function (): void {
    $code = 'for ($i = 0; $i < 70; $i++) { fwrite(STDOUT, str_repeat("A", 1000)); fwrite(STDERR, str_repeat("B", 1000)); } exit(0);';

    $result = (new ProcessSupervisor())->run(new PreparedCommand(
        tool: 'php',
        binary: PHP_BINARY,
        arguments: ['-r', $code],
        cwd: getcwd() ?: '.',
    ), timeoutSeconds: 5.0);

    expect($result->exitCode())->toBe(0);
    expect(strlen($result->stdout()))->toBe(70_000);
    expect(strlen($result->stderr()))->toBe(70_000);
});

it('returns a timeout result and runs cleanup callbacks', function (): void {
    $cleaned = false;

    $result = (new ProcessSupervisor())->run(
        command: new PreparedCommand(
            tool: 'php',
            binary: PHP_BINARY,
            arguments: ['-r', 'usleep(500000); fwrite(STDOUT, "late");'],
            cwd: getcwd() ?: '.',
        ),
        timeoutSeconds: 0.05,
        cleanupCallbacks: [
            function () use (&$cleaned): void {
                $cleaned = true;
            },
        ],
    );

    expect($result->timedOut())->toBeTrue();
    expect($result->exitCode())->toBe(2);
    expect($result->errorCode())->toBe(ErrorCode::ProcessTimeout);
    expect($cleaned)->toBeTrue();
});

it('returns an interruption result and runs cleanup callbacks', function (): void {
    $cleaned = false;

    $result = (new ProcessSupervisor(interruptionChecker: static fn(): bool => true))->run(
        command: new PreparedCommand(
            tool: 'php',
            binary: PHP_BINARY,
            arguments: ['-r', 'sleep(3);'],
            cwd: getcwd() ?: '.',
        ),
        timeoutSeconds: 5.0,
        cleanupCallbacks: [
            function () use (&$cleaned): void {
                $cleaned = true;
            },
        ],
    );

    expect($result->interrupted())->toBeTrue();
    expect($result->exitCode())->toBe(130);
    expect($result->errorCode())->toBe(ErrorCode::ProcessInterrupted);
    expect($cleaned)->toBeTrue();
});
