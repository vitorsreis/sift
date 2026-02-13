<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Symfony\Component\Process\Process;

final class ProcessExecutor
{
    public function run(PreparedCommand $preparedCommand, ?callable $outputCallback = null): ExecutionResult
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
        $process->run(function (string $type, string $buffer) use (&$stdout, &$stderr, $outputCallback): void {
            if ($type === Process::ERR) {
                $stderr .= $buffer;

                if ($outputCallback !== null) {
                    $outputCallback($type, $buffer);
                }

                return;
            }

            $stdout .= $buffer;

            if ($outputCallback !== null) {
                $outputCallback($type, $buffer);
            }
        });

        return new ExecutionResult(
            exitCode: $process->getExitCode() ?? 1,
            stdout: $stdout,
            stderr: $stderr,
            duration: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }
}
