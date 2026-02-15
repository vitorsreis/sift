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

final readonly class PintToolAdapter implements ToolAdapterInterface
{
    use DecodesJsonOutput;
    use DetectsCliOptions;
    use ResolvesToolCandidates;

    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function name(): string
    {
        return 'pint';
    }

    public function installHint(): string
    {
        return 'Install it with: composer require --dev laravel/pint';
    }

    /**
     * @return list<string>
     */
    public function discoveryCandidates(): array
    {
        return [
            'vendor/bin/pint.bat',
            'vendor/bin/pint',
            'pint',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function initConfig(): array
    {
        return [
            'enabled' => true,
            'defaultArgs' => ['--test'],
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
            'mode' => $this->mode($arguments),
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

        if (! $this->hasOption($arguments, '--format')) {
            $arguments[] = '--format=json';
        }

        $mode = $this->mode($arguments);

        if ($mode === 'fix') {
            $arguments[] = '--test';
            $mode = 'test';
        }

        return new PreparedCommand(
            command: [...$resolved['command_prefix'], ...$arguments],
            cwd: $cwd,
            metadata: [
                ...$context,
                'mode' => $mode,
            ],
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
        $decoded = $this->decodeJsonOutput($executionResult, allowNoisy: true);

        if (! is_array($decoded)) {
            throw UserFacingException::parseFailure($this->name(), 'Unable to parse Pint JSON output.');
        }

        $files = is_array($decoded['files'] ?? null) ? $decoded['files'] : [];
        $items = [];
        $totalFixers = 0;

        foreach ($files as $file) {
            if (! is_array($file)) {
                continue;
            }

            $fixers = array_values(array_filter(
                is_array($file['fixers'] ?? null) ? $file['fixers'] : [],
                static fn (mixed $fixer): bool => is_string($fixer) && $fixer !== '',
            ));

            $path = str_replace('\\', '/', (string) ($file['path'] ?? ''));

            if ($path === '' && $fixers === []) {
                continue;
            }

            $totalFixers += count($fixers);
            $items[] = [
                'file' => $path,
                'fixers' => $fixers,
            ];
        }

        $status = match ($decoded['result'] ?? null) {
            'pass' => 'passed',
            'fail' => 'failed',
            default => $executionResult->exitCode === 0 ? 'passed' : 'error',
        };

        return new NormalizedResult(
            tool: $this->name(),
            status: $status,
            summary: [
                'files' => count($items),
                'fixers' => $totalFixers,
            ],
            items: $items,
            meta: [
                'exit_code' => $executionResult->exitCode,
                'duration' => $executionResult->duration,
                'command' => $preparedCommand->command,
                'mode' => (string) ($preparedCommand->metadata['mode'] ?? $context['mode'] ?? 'test'),
            ],
        );
    }

    /**
     * @param  list<string>  $arguments
     */
    private function mode(array $arguments): string
    {
        foreach ($arguments as $argument) {
            if ($argument === '--test' || $argument === '--bail') {
                return 'test';
            }

            if ($argument === '--repair') {
                return 'repair';
            }
        }

        return 'fix';
    }
}
