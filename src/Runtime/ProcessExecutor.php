<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Symfony\Component\Process\Process;

final class ProcessExecutor
{
    public function run(PreparedCommand $preparedCommand): ExecutionResult
    {
        $startedAt = microtime(true);
        $stdout = '';
        $stderr = '';

        $process = new Process(
            command: $preparedCommand->command,
            cwd: $preparedCommand->cwd,
            env: $preparedCommand->env,
        );

        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer) use (&$stdout, &$stderr): void {
            if ($type === Process::ERR) {
                $stderr .= $buffer;

                return;
            }

            $stdout .= $buffer;
        });

        return new ExecutionResult(
            exitCode: $process->getExitCode() ?? 1,
            stdout: $stdout,
            stderr: $stderr,
            duration: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }
}
