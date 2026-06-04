<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;

final class FakeProcessRunner
{
    /**
     * @var list<PreparedCommand>
     */
    private array $commands = [];

    /**
     * @param list<ExecutionResult> $results
     */
    public function __construct(
        private array $results = [],
    ) {}

    public static function withResult(ExecutionResult $result): self
    {
        return new self([$result]);
    }

    public function push(ExecutionResult $result): void
    {
        $this->results[] = $result;
    }

    public function run(PreparedCommand $command): ExecutionResult
    {
        $this->commands[] = $command;
        $result = array_shift($this->results);

        if (! $result instanceof ExecutionResult) {
            throw new RuntimeException('FakeProcessRunner has no queued result.');
        }

        return $result;
    }

    /**
     * @return list<PreparedCommand>
     */
    public function commands(): array
    {
        return $this->commands;
    }
}
