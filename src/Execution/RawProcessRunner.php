<?php

declare(strict_types=1);

namespace Sift\Execution;

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;

final readonly class RawProcessRunner
{
    public function __construct(
        private ProcessSupervisor $supervisor = new ProcessSupervisor(),
    ) {}

    public function run(
        PreparedCommand $command,
        mixed $stdout = null,
        mixed $stderr = null,
    ): ExecutionResult {
        return $this->supervisor->runStreaming(
            command: $command,
            timeoutSeconds: (float) $command->timeout(),
            stdout: $stdout ?? STDOUT,
            stderr: $stderr ?? STDERR,
        );
    }
}
