<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;

final readonly class ComposerAuditToolAdapter implements ToolAdapterInterface
{
    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function name(): string
    {
        return 'composer-audit';
    }

    public function installHint(): string
    {
        return 'Install Composer first and make sure the `composer` command is available.';
    }

    /**
     * @return list<string>
     */
    public function discoveryCandidates(): array
    {
        return ['composer'];
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
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, mixed>  $context
     */
    public function prepare(string $cwd, array $arguments, array $context): PreparedCommand
    {
        $resolved = $this->toolLocator->locate($cwd, $this->discoveryCandidates());

        if ($resolved === null) {
            throw UserFacingException::toolNotInstalled($this->name(), $this->installHint());
        }

        $command = [...$resolved['command_prefix'], 'audit'];

        if (! $this->hasOption($arguments, '--format')) {
            $command[] = '--format=json';
        }

        return new PreparedCommand(
            command: [...$command, ...$arguments],
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

        $advisories = is_array($decoded['advisories'] ?? null) ? $decoded['advisories'] : [];
        $items = [];

        foreach ($advisories as $package => $packageAdvisories) {
            if (! is_array($packageAdvisories)) {
                continue;
            }

            foreach ($packageAdvisories as $advisory) {
                if (! is_array($advisory)) {
                    continue;
                }

                $items[] = [
                    'package' => (string) $package,
                    'severity' => (string) ($advisory['severity'] ?? 'unknown'),
                    'advisory_id' => (string) ($advisory['advisoryId'] ?? ''),
                    'title' => (string) ($advisory['title'] ?? ''),
                    'cve' => (string) ($advisory['cve'] ?? ''),
                    'link' => (string) ($advisory['link'] ?? ''),
                ];
            }
        }

        return new NormalizedResult(
            tool: $this->name(),
            status: $items === [] ? 'passed' : 'failed',
            summary: [
                'vulnerabilities' => count($items),
                'packages' => count($advisories),
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
}
