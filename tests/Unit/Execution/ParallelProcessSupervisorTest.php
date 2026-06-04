<?php

declare(strict_types=1);

use Sift\Core\PreparedCommand;
use Sift\Execution\ParallelProcessSupervisor;

it('runs process jobs concurrently and preserves result order', function (): void {
    $commands = [
        new PreparedCommand('slow', PHP_BINARY, ['-r', 'usleep(150000); echo "slow";'], getcwd() ?: '.', timeout: 2),
        new PreparedCommand('fast', PHP_BINARY, ['-r', 'echo "fast";'], getcwd() ?: '.', timeout: 2),
    ];

    $startedAt = microtime(true);
    $results = (new ParallelProcessSupervisor())->run($commands);

    expect(microtime(true) - $startedAt)->toBeLessThan(0.28);
    expect($results)->toHaveCount(2);
    expect($results[0]->stdout())->toBe('slow');
    expect($results[1]->stdout())->toBe('fast');
});

it('applies each parallel process timeout', function (): void {
    $results = (new ParallelProcessSupervisor())->run([
        new PreparedCommand('timeout', PHP_BINARY, ['-r', 'usleep(2000000);'], getcwd() ?: '.', timeout: 1),
    ]);

    expect($results[0]->timedOut())->toBeTrue();
});
