<?php

declare(strict_types=1);

namespace Sift\Execution;

use InvalidArgumentException;
use Sift\Core\Clock;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Core\SystemClock;
use Sift\Exceptions\UserFacingException;

final readonly class ProcessSupervisor
{
    public function __construct(
        private ProcessCommandBuilder $commandBuilder = new ProcessCommandBuilder(),
        private Clock $clock = new SystemClock(),
        private ProcessTreeTerminator $processTerminator = new ProcessTreeTerminator(),
    ) {}

    /**
     * @param list<callable(): void> $cleanupCallbacks
     */
    public function run(
        PreparedCommand $command,
        float $timeoutSeconds,
        array $cleanupCallbacks = [],
    ): ExecutionResult {
        $startedAt = $this->clock->monotonicSeconds();
        $stdoutPath = $this->temporaryPath('sift-out-');
        $stderrPath = $this->temporaryPath('sift-err-');
        $pipes = [];
        $closed = false;
        $process = @proc_open(
            $this->commandBuilder->argv($command),
            [
                0 => ['pipe', 'r'],
                1 => ['file', $stdoutPath, 'w'],
                2 => ['file', $stderrPath, 'w'],
            ],
            $pipes,
            $command->cwd(),
            $command->environment() === [] ? null : $command->environment(),
        );

        if (! is_resource($process)) {
            $this->removeFile($stdoutPath);
            $this->removeFile($stderrPath);

            throw UserFacingException::withContext(
                errorCode: ErrorCode::ProcessFailed,
                message: sprintf('Could not start process for tool "%s".', $command->tool()),
                context: ['tool' => $command->tool(), 'command' => $command->argv()],
            );
        }

        try {
            $this->closePipe($pipes[0] ?? null);
            $exitCode = null;

            while (true) {
                $status = proc_get_status($process);

                if ($status['running'] && $timeoutSeconds > 0 && ($this->clock->monotonicSeconds() - $startedAt) >= $timeoutSeconds) {
                    $this->processTerminator->terminate($process);
                    proc_close($process);
                    $closed = true;

                    return ExecutionResult::timeout(
                        stdout: $this->readFile($stdoutPath),
                        stderr: $this->readFile($stderrPath),
                        durationSeconds: $this->clock->monotonicSeconds() - $startedAt,
                    );
                }

                if (! $status['running']) {
                    $exitCode = $status['exitcode'] >= 0
                        ? $status['exitcode']
                        : null;

                    break;
                }

                usleep(10_000);
            }

            $closedExitCode = proc_close($process);
            $closed = true;

            return ExecutionResult::completed(
                exitCode: $exitCode ?? $closedExitCode,
                stdout: $this->readFile($stdoutPath),
                stderr: $this->readFile($stderrPath),
                durationSeconds: $this->clock->monotonicSeconds() - $startedAt,
            );
        } finally {
            $this->closePipe($pipes[0] ?? null);

            if (! $closed) {
                proc_close($process);
            }

            $this->removeFile($stdoutPath);
            $this->removeFile($stderrPath);

            foreach ($cleanupCallbacks as $cleanupCallback) {
                $cleanupCallback();
            }
        }
    }

    /**
     * @param list<callable(): void> $cleanupCallbacks
     */
    public function runStreaming(
        PreparedCommand $command,
        float $timeoutSeconds,
        mixed $stdout,
        mixed $stderr,
        array $cleanupCallbacks = [],
    ): ExecutionResult {
        if (! is_resource($stdout) || ! is_resource($stderr)) {
            throw new InvalidArgumentException('Raw process streams must be resources.');
        }

        $startedAt = $this->clock->monotonicSeconds();
        $pipes = [];
        $closed = false;
        $process = @proc_open(
            $this->commandBuilder->argv($command),
            [
                0 => ['pipe', 'r'],
                1 => $stdout,
                2 => $stderr,
            ],
            $pipes,
            $command->cwd(),
            $command->environment() === [] ? null : $command->environment(),
        );

        if (! is_resource($process)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::ProcessFailed,
                message: sprintf('Could not start process for tool "%s".', $command->tool()),
                context: ['tool' => $command->tool(), 'command' => $command->argv()],
            );
        }

        try {
            $this->closePipe($pipes[0] ?? null);
            $exitCode = null;

            while (true) {
                $status = proc_get_status($process);

                if ($status['running'] && $timeoutSeconds > 0 && ($this->clock->monotonicSeconds() - $startedAt) >= $timeoutSeconds) {
                    $this->processTerminator->terminate($process);
                    proc_close($process);
                    $closed = true;

                    return ExecutionResult::timeout(
                        stdout: '',
                        stderr: '',
                        durationSeconds: $this->clock->monotonicSeconds() - $startedAt,
                    );
                }

                if (! $status['running']) {
                    $exitCode = $status['exitcode'] >= 0
                        ? $status['exitcode']
                        : null;

                    break;
                }

                usleep(10_000);
            }

            $closedExitCode = proc_close($process);
            $closed = true;

            return ExecutionResult::completed(
                exitCode: $exitCode ?? $closedExitCode,
                stdout: '',
                stderr: '',
                durationSeconds: $this->clock->monotonicSeconds() - $startedAt,
            );
        } finally {
            $this->closePipe($pipes[0] ?? null);

            if (! $closed) {
                proc_close($process);
            }

            foreach ($cleanupCallbacks as $cleanupCallback) {
                $cleanupCallback();
            }
        }
    }

    private function temporaryPath(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: 'Could not create process output spool file.',
            );
        }

        return $path;
    }

    private function closePipe(mixed $pipe): void
    {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : '';
    }

    private function removeFile(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
