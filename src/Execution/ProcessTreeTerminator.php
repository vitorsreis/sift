<?php

declare(strict_types=1);

namespace Sift\Execution;

final readonly class ProcessTreeTerminator
{
    public function __construct(
        private Platform $platform = new Platform(),
    ) {}

    /**
     * @param resource $process
     */
    public function terminate(mixed $process): void
    {
        $status = proc_get_status($process);
        $pid = $status['pid'];

        if ($pid > 0) {
            if ($this->platform->isWindows()) {
                $this->runUtility(['taskkill.exe', '/PID', (string) $pid, '/T', '/F']);
            } else {
                foreach (array_reverse($this->descendantPids($pid)) as $descendantPid) {
                    $this->terminatePid($descendantPid);
                }
            }
        }

        if ($status['running']) {
            @proc_terminate($process);
        }
    }

    /**
     * @return list<int>
     */
    private function descendantPids(int $rootPid): array
    {
        $output = $this->runUtility(['ps', '-eo', 'pid=,ppid=']);
        $childrenByParent = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $line, $matches) !== 1) {
                continue;
            }

            $childrenByParent[(int) $matches[2]][] = (int) $matches[1];
        }

        $descendants = [];
        $pending = [$rootPid];

        while ($pending !== []) {
            $parent = array_pop($pending);

            foreach ($childrenByParent[$parent] ?? [] as $child) {
                $descendants[] = $child;
                $pending[] = $child;
            }
        }

        return $descendants;
    }

    private function terminatePid(int $pid): void
    {
        if (function_exists('posix_kill')) {
            @posix_kill($pid, 15);

            return;
        }

        $this->runUtility(['kill', '-TERM', (string) $pid]);
    }

    /**
     * @param non-empty-list<string> $argv
     */
    private function runUtility(array $argv): string
    {
        $pipes = [];
        $process = @proc_open(
            $argv,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (! is_resource($process)) {
            return '';
        }

        if (is_resource($pipes[0] ?? null)) {
            fclose($pipes[0]);
        }

        $stdout = is_resource($pipes[1] ?? null) ? stream_get_contents($pipes[1]) : '';

        foreach ([1, 2] as $index) {
            if (is_resource($pipes[$index] ?? null)) {
                fclose($pipes[$index]);
            }
        }

        proc_close($process);

        return is_string($stdout) ? $stdout : '';
    }
}
