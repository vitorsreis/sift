<?php

declare(strict_types=1);

use Sift\Core\PreparedCommand;
use Sift\Execution\ParallelProcessSupervisor;

it('runs process jobs concurrently and preserves result order', function (): void {
    $firstMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sift-parallel-first-' . bin2hex(random_bytes(6));
    $secondMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sift-parallel-second-' . bin2hex(random_bytes(6));
    $waitForPeer = static fn(string $own, string $peer, string $output): string => sprintf(
        'file_put_contents(%s, "1"); $deadline = microtime(true) + 5; while (!file_exists(%s) && microtime(true) < $deadline) { usleep(10000); } echo file_exists(%s) ? %s : "missing";',
        var_export($own, true),
        var_export($peer, true),
        var_export($peer, true),
        var_export($output, true),
    );
    $commands = [
        new PreparedCommand('first', PHP_BINARY, ['-r', $waitForPeer($firstMarker, $secondMarker, 'first')], getcwd() ?: '.', timeout: 10),
        new PreparedCommand('second', PHP_BINARY, ['-r', $waitForPeer($secondMarker, $firstMarker, 'second')], getcwd() ?: '.', timeout: 10),
    ];

    try {
        $results = (new ParallelProcessSupervisor())->run($commands);
    } finally {
        @unlink($firstMarker);
        @unlink($secondMarker);
    }

    expect($results)->toHaveCount(2);
    expect($results[0]->stdout())->toBe('first');
    expect($results[1]->stdout())->toBe('second');
});

it('applies each parallel process timeout', function (): void {
    $results = (new ParallelProcessSupervisor())->run([
        new PreparedCommand('timeout', PHP_BINARY, ['-r', 'usleep(2000000);'], getcwd() ?: '.', timeout: 1),
    ]);

    expect($results[0]->timedOut())->toBeTrue();
});
