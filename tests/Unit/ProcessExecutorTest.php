<?php

declare(strict_types=1);

use Sift\Core\PreparedCommand;
use Sift\Runtime\ProcessExecutor;
use Symfony\Component\Process\Process;

it('captures stdout stderr duration and callback output from executed commands', function (): void {
    $executor = new ProcessExecutor;
    $chunks = [];
    $command = new PreparedCommand(
        command: [
            PHP_BINARY,
            '-r',
            'fwrite(STDOUT, "alpha\n"); fwrite(STDERR, "beta\n");',
        ],
        cwd: siftRoot(),
        env: siftTestEnvironment(),
    );

    $result = $executor->run($command, function (string $type, string $buffer) use (&$chunks): void {
        $chunks[] = [$type, $buffer];
    });

    expect($result->exitCode)->toBe(0)
        ->and($result->stdout)->toBe("alpha\n")
        ->and($result->stderr)->toBe("beta\n")
        ->and($result->duration)->toBeInt()
        ->and($result->duration)->toBeGreaterThanOrEqual(0)
        ->and($chunks)->toContain([Process::OUT, "alpha\n"], [Process::ERR, "beta\n"]);
});
