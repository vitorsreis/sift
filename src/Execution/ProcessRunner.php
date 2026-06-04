<?php

declare(strict_types=1);

namespace Sift\Execution;

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;

final readonly class ProcessRunner
{
    public function __construct(
        private ProcessSupervisor $supervisor = new ProcessSupervisor(),
    ) {}

    public function run(PreparedCommand $command): ExecutionResult
    {
        return $this->supervisor->run(
            command: $command,
            timeoutSeconds: (float) $command->timeout(),
        );
    }
}
