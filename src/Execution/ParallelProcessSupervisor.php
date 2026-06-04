<?php

declare(strict_types=1);

namespace Sift\Execution;

use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;

final readonly class ParallelProcessSupervisor
{
    public function __construct(
        private ProcessCommandBuilder $commandBuilder = new ProcessCommandBuilder(),
        private ProcessTreeTerminator $processTerminator = new ProcessTreeTerminator(),
    ) {}

    /**
     * @param list<PreparedCommand> $commands
     *
     * @return list<ExecutionResult>
     */
    public function run(array $commands): array
    {
        $running = [];
        $results = [];

        foreach ($commands as $index => $command) {
            $running[$index] = $this->start($command);
        }

        while ($running !== []) {
            foreach ($running as $index => $job) {
                if (! is_resource($job['process'])) {
                    $results[$index] = ExecutionResult::completed(1, '', '', microtime(true) - $job['started_at']);
                    $this->cleanup($job);
                    unset($running[$index]);

                    continue;
                }

                if ($this->timedOut($job)) {
                    $this->processTerminator->terminate($job['process']);
                    proc_close($job['process']);
                    $results[$index] = ExecutionResult::timeout(
                        $this->read($job['stdout_path']),
                        $this->read($job['stderr_path']),
                        microtime(true) - $job['started_at'],
                    );
                    $this->cleanup($job);
                    unset($running[$index]);

                    continue;
                }

                $status = proc_get_status($job['process']);

                if ($status['running']) {
                    continue;
                }

                $exitCode = $status['exitcode'] >= 0 ? $status['exitcode'] : proc_close($job['process']);

                if ($status['exitcode'] >= 0) {
                    proc_close($job['process']);
                }

                $results[$index] = ExecutionResult::completed(
                    $exitCode,
                    $this->read($job['stdout_path']),
                    $this->read($job['stderr_path']),
                    microtime(true) - $job['started_at'],
                );
                $this->cleanup($job);
                unset($running[$index]);
            }

            if ($running !== []) {
                usleep(10_000);
            }
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * @return array{process: resource|null, stdout_path: string, stderr_path: string, started_at: float, timeout: int}
     */
    private function start(PreparedCommand $command): array
    {
        $stdoutPath = $this->temporaryPath('sift-parallel-out-');
        $stderrPath = $this->temporaryPath('sift-parallel-err-');
        $pipes = [];
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

        if (is_resource($pipes[0] ?? null)) {
            fclose($pipes[0]);
        }

        return [
            'process' => is_resource($process) ? $process : null,
            'stdout_path' => $stdoutPath,
            'stderr_path' => $stderrPath,
            'started_at' => microtime(true),
            'timeout' => $command->timeout(),
        ];
    }

    /**
     * @param array{started_at: float, timeout: int} $job
     */
    private function timedOut(array $job): bool
    {
        return $job['timeout'] > 0 && microtime(true) - $job['started_at'] >= $job['timeout'];
    }

    /**
     * @param array{stdout_path: string, stderr_path: string} $job
     */
    private function cleanup(array $job): void
    {
        @unlink($job['stdout_path']);
        @unlink($job['stderr_path']);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : '';
    }

    private function temporaryPath(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: 'Could not create parallel process output spool file.',
            );
        }

        return $path;
    }
}
