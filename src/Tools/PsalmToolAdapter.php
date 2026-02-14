<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;

final readonly class PsalmToolAdapter implements ToolAdapterInterface
{
    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function name(): string
    {
        return 'psalm';
    }

    public function installHint(): string
    {
        return 'Install it with: composer require --dev vimeo/psalm';
    }

    /**
     * @return list<string>
     */
    public function discoveryCandidates(): array
    {
        return [
            'vendor/bin/psalm.bat',
            'vendor/bin/psalm',
            'psalm',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function initConfig(): array
    {
        return [
            'enabled' => true,
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

        if (! $this->hasOption($arguments, '--output-format')) {
            $arguments[] = '--output-format=json';
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
            throw UserFacingException::parseFailure($this->name(), 'Unable to parse Psalm JSON output.');
        }

        $items = [];
        $files = [];

        foreach ($decoded as $issue) {
            if (! is_array($issue)) {
                continue;
            }

            $file = str_replace('\\', '/', (string) ($issue['file_path'] ?? $issue['file_name'] ?? ''));

            if ($file !== '') {
                $files[$file] = true;
            }

            $items[] = [
                'type' => strtolower((string) ($issue['severity'] ?? 'error')),
                'rule' => (string) ($issue['type'] ?? ''),
                'message' => (string) ($issue['message'] ?? ''),
                'file' => $file,
                'line' => (int) ($issue['line_from'] ?? 0),
                'column' => (int) ($issue['column_from'] ?? 0),
            ];
        }

        return new NormalizedResult(
            tool: $this->name(),
            status: $items === [] ? 'passed' : 'failed',
            summary: [
                'issues' => count($items),
                'files' => count($files),
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
