<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;
use Sift\Tools\Concerns\DecodesJsonOutput;
use Sift\Tools\Concerns\DetectsCliOptions;
use Sift\Tools\Concerns\ResolvesToolCandidates;

final readonly class PhpstanToolAdapter implements ToolAdapterInterface
{
    use DecodesJsonOutput;
    use DetectsCliOptions;
    use ResolvesToolCandidates;

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
        $resolved = $this->toolLocator->locate($cwd, $this->resolveCandidates($context, $this->discoveryCandidates()));

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
        $decoded = $this->decodeJsonOutput($executionResult);

        if (! is_array($decoded)) {
            throw UserFacingException::parseFailure($this->name(), 'Unable to parse PHPStan JSON output.');
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
}
