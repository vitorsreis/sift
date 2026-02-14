<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;

final readonly class PhpcsToolAdapter implements ToolAdapterInterface
{
    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function name(): string
    {
        return 'phpcs';
    }

    public function installHint(): string
    {
        return 'Install it with: composer require --dev squizlabs/php_codesniffer';
    }

    /**
     * @return list<string>
     */
    public function discoveryCandidates(): array
    {
        return [
            'vendor/bin/phpcs.bat',
            'vendor/bin/phpcs',
            'phpcs',
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

        if (! $this->hasOption($arguments, '--report')) {
            $arguments[] = '--report=json';
        }

        if (! $this->hasShortOption($arguments, '-q')) {
            $arguments[] = '-q';
        }

        if (! $this->hasOption($arguments, '--no-colors')) {
            $arguments[] = '--no-colors';
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
            throw UserFacingException::parseFailure($this->name(), 'Unable to parse PHPCS JSON output.');
        }

        $totals = is_array($decoded['totals'] ?? null) ? $decoded['totals'] : [];
        $files = is_array($decoded['files'] ?? null) ? $decoded['files'] : [];
        $items = [];

        foreach ($files as $file => $details) {
            if (! is_array($details)) {
                continue;
            }

            $messages = is_array($details['messages'] ?? null) ? $details['messages'] : [];

            foreach ($messages as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $items[] = [
                    'type' => strtolower((string) ($message['type'] ?? 'error')),
                    'file' => str_replace('\\', '/', (string) $file),
                    'line' => (int) ($message['line'] ?? 0),
                    'column' => (int) ($message['column'] ?? 0),
                    'message' => (string) ($message['message'] ?? ''),
                    'rule' => (string) ($message['source'] ?? ''),
                    'fixable' => (bool) ($message['fixable'] ?? false),
                ];
            }
        }

        return new NormalizedResult(
            tool: $this->name(),
            status: ((int) ($totals['errors'] ?? 0)) > 0 || ((int) ($totals['warnings'] ?? 0)) > 0 ? 'failed' : 'passed',
            summary: [
                'errors' => (int) ($totals['errors'] ?? 0),
                'warnings' => (int) ($totals['warnings'] ?? 0),
                'fixable' => (int) ($totals['fixable'] ?? 0),
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
     * @param  list<string>  $arguments
     */
    private function hasShortOption(array $arguments, string $option): bool
    {
        return in_array($option, $arguments, true);
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
