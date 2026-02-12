<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;

final readonly class PhpstanToolAdapter implements ToolAdapterInterface
{
    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function name(): string
    {
        return 'phpstan';
    }

    public function installHint(): string
    {
        return 'Install it with: composer require --dev phpstan/phpstan';
    }

    /**
     * @return list<string>
     */
    public function discoveryCandidates(): array
    {
        return [
            'vendor/bin/phpstan.bat',
            'vendor/bin/phpstan',
            'phpstan',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function initConfig(): array
    {
        return [
            'enabled' => true,
            'defaultArgs' => ['analyse'],
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    public function detectContext(array $arguments): array
    {
        return [
            'arguments' => $arguments,
            'has_paths' => $arguments !== [],
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, mixed>  $context
     */
    public function prepare(string $cwd, array $arguments, array $context): PreparedCommand
    {
        $resolved = $this->toolLocator->locate($cwd, $this->resolveCandidates($context));

        if ($resolved === null) {
            throw UserFacingException::toolNotInstalled($this->name(), $this->installHint());
        }

        if ($arguments === []) {
            $arguments = ['analyse'];
        }

        if (! $this->hasOption($arguments, '--error-format')) {
            $arguments[] = '--error-format=json';
        }

        if (! $this->hasOption($arguments, '--no-progress')) {
            $arguments[] = '--no-progress';
        }

        return new PreparedCommand(
            command: [...$resolved['command_prefix'], ...$arguments],
            cwd: $cwd,
            metadata: $context,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function parse(
        ExecutionResult $executionResult,
        PreparedCommand $preparedCommand,
        array $context,
    ): NormalizedResult {
        $decoded = json_decode($executionResult->stdout, true);

        if (! is_array($decoded)) {
            return new NormalizedResult(
                tool: $this->name(),
                status: $executionResult->exitCode === 0 ? 'passed' : 'error',
                summary: [
                    'errors' => 0,
                    'files' => 0,
                ],
                extra: [
                    'stdout' => trim($executionResult->stdout),
                    'stderr' => trim($executionResult->stderr),
                ],
                meta: [
                    'exit_code' => $executionResult->exitCode,
                    'duration' => $executionResult->duration,
                    'command' => $preparedCommand->command,
                ],
            );
        }

        $totals = is_array($decoded['totals'] ?? null) ? $decoded['totals'] : [];
        $files = is_array($decoded['files'] ?? null) ? $decoded['files'] : [];
        $items = [];

        foreach ($files as $file => $fileData) {
            if (! is_array($fileData)) {
                continue;
            }

            $messages = is_array($fileData['messages'] ?? null) ? $fileData['messages'] : [];

            foreach ($messages as $message) {
                if (! is_string($message) || $message === '') {
                    continue;
                }

                $items[] = [
                    'file' => str_replace('\\', '/', (string) $file),
                    'message' => $message,
                ];
            }
        }

        return new NormalizedResult(
            tool: $this->name(),
            status: ((int) ($totals['errors'] ?? 0)) > 0 || ((int) ($totals['file_errors'] ?? 0)) > 0 ? 'failed' : 'passed',
            summary: [
                'errors' => (int) ($totals['errors'] ?? 0),
                'files' => (int) ($totals['file_errors'] ?? 0),
            ],
            items: $items,
            meta: [
                'exit_code' => $executionResult->exitCode,
                'duration' => $executionResult->duration,
                'command' => $preparedCommand->command,
            ],
        );
    }

    /**
     * @param  list<string>  $arguments
     */
    private function hasOption(array $arguments, string $option): bool
    {
        foreach ($arguments as $argument) {
            if ($argument === $option || str_starts_with($argument, $option.'=')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private function resolveCandidates(array $context): array
    {
        $configured = is_string($context['tool_binary'] ?? null) && trim((string) $context['tool_binary']) !== ''
            ? [trim((string) $context['tool_binary'])]
            : [];

        return $configured !== [] ? $configured : $this->discoveryCandidates();
    }
}
