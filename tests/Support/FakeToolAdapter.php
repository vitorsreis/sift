<?php

declare(strict_types=1);

namespace Tests\Support;

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;

final readonly class FakeToolAdapter implements ToolAdapterInterface
{
    /**
     * @param  list<string>  $discoveryCandidates
     * @param  array<string, mixed>  $initConfig
     */
    public function __construct(
        private string $name,
        private string $installHint,
        private array $discoveryCandidates,
        private array $initConfig = ['enabled' => true],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function installHint(): string
    {
        return $this->installHint;
    }

    public function discoveryCandidates(): array
    {
        return $this->discoveryCandidates;
    }

    public function initConfig(): array
    {
        return $this->initConfig;
    }

    public function detectContext(array $arguments): array
    {
        return ['arguments' => $arguments];
    }

    public function prepare(string $cwd, array $arguments, array $context): PreparedCommand
    {
        return new PreparedCommand(
            command: [...$this->discoveryCandidates, ...$arguments],
            cwd: $cwd,
            metadata: $context,
        );
    }

    public function parse(
        ExecutionResult $executionResult,
        PreparedCommand $preparedCommand,
        array $context,
    ): NormalizedResult {
        return new NormalizedResult(
            tool: $this->name,
            status: $executionResult->exitCode === 0 ? 'passed' : 'failed',
            meta: [
                'command' => $preparedCommand->command,
                ...$context,
            ],
        );
    }
}
