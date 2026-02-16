<?php

declare(strict_types=1);

namespace Tests\Support;

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;
use Sift\Tools\Concerns\ResolvesToolCandidates;

final readonly class DummyExecutableToolAdapter implements ToolAdapterInterface
{
    use ResolvesToolCandidates;

    public function __construct(
        private ToolLocator $toolLocator,
        private string $name = 'dummy',
        private string $candidate = 'vendor/bin/dummy.php',
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function installHint(): string
    {
        return 'Install the dummy test binary.';
    }

    public function discoveryCandidates(): array
    {
        return [$this->candidate];
    }

    public function initConfig(): array
    {
        return [
            'enabled' => true,
        ];
    }

    public function detectContext(array $arguments): array
    {
        return [
            'arguments' => $arguments,
        ];
    }

    public function prepare(string $cwd, array $arguments, array $context): PreparedCommand
    {
        $resolved = $this->toolLocator->locate($cwd, $this->resolveCandidates($context, $this->discoveryCandidates()));

        if ($resolved === null) {
            throw UserFacingException::toolNotInstalled($this->name(), $this->installHint());
        }

        return new PreparedCommand(
            command: [...$resolved['command_prefix'], ...$arguments],
            cwd: $cwd,
            env: [
                'XDEBUG_MODE' => 'off',
                'XDEBUG_START_WITH_REQUEST' => 'no',
            ],
            metadata: [
                ...$context,
                'resolved_path' => $resolved['path'],
            ],
        );
    }

    public function parse(
        ExecutionResult $executionResult,
        PreparedCommand $preparedCommand,
        array $context,
    ): NormalizedResult {
        $items = [];

        if ($executionResult->stdout !== '') {
            $items[] = [
                'stdout' => trim($executionResult->stdout),
            ];
        }

        if ($executionResult->stderr !== '') {
            $items[] = [
                'stderr' => trim($executionResult->stderr),
            ];
        }

        return new NormalizedResult(
            tool: $this->name(),
            status: $executionResult->exitCode === 0 ? 'passed' : 'failed',
            summary: [
                'exit_code' => $executionResult->exitCode,
            ],
            items: $items,
            meta: [
                'duration' => $executionResult->duration,
                'command' => $preparedCommand->command,
                'resolved_path' => $preparedCommand->metadata['resolved_path'] ?? null,
                'arguments' => $context['arguments'] ?? [],
            ],
        );
    }
}
