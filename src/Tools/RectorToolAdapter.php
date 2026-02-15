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

final readonly class RectorToolAdapter implements ToolAdapterInterface
{
    use DecodesJsonOutput;
    use DetectsCliOptions;
    use ResolvesToolCandidates;

    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function name(): string
    {
        return 'rector';
    }

    public function installHint(): string
    {
        return 'Install it with: composer require --dev rector/rector';
    }

    /**
     * @return list<string>
     */
    public function discoveryCandidates(): array
    {
        return [
            'vendor/bin/rector.bat',
            'vendor/bin/rector',
            'rector',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function initConfig(): array
    {
        return [
            'enabled' => true,
            'defaultArgs' => ['process', '--dry-run'],
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
            'command' => $this->command($arguments),
            'dry_run' => $this->hasOption($arguments, '--dry-run'),
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

        if ($arguments === [] || str_starts_with($arguments[0] ?? '', '-')) {
            array_unshift($arguments, 'process');
        }

        if (! $this->hasOption($arguments, '--output-format')) {
            $arguments[] = '--output-format=json';
        }

        return new PreparedCommand(
            command: [...$resolved['command_prefix'], ...$arguments],
            cwd: $cwd,
            metadata: [
                ...$context,
                'command' => $this->command($arguments),
                'dry_run' => $this->hasOption($arguments, '--dry-run'),
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
            throw UserFacingException::parseFailure($this->name(), 'Unable to parse Rector JSON output.');
        }

        $dryRun = (bool) ($preparedCommand->metadata['dry_run'] ?? $context['dry_run'] ?? false);
        $fileDiffs = array_values(array_filter(
            is_array($decoded['file_diffs'] ?? null) ? $decoded['file_diffs'] : [],
            static fn (mixed $fileDiff): bool => is_array($fileDiff),
        ));
        $errors = array_values(array_filter(
            is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [],
            static fn (mixed $error): bool => is_array($error),
        ));

        $items = [];
        $artifacts = [];

        foreach ($fileDiffs as $fileDiff) {
            $appliedRectors = array_values(array_filter(
                is_array($fileDiff['applied_rectors'] ?? null) ? $fileDiff['applied_rectors'] : [],
                static fn (mixed $rector): bool => is_string($rector) && $rector !== '',
            ));

            $artifact = [
                'file' => $this->normalizePath((string) ($fileDiff['file'] ?? '')),
                'diff' => (string) ($fileDiff['diff'] ?? ''),
                'applied_rectors' => $appliedRectors,
            ];

            $artifacts[] = $artifact;
            $items[] = [
                'type' => 'change',
                'file' => $artifact['file'],
                'message' => $dryRun ? 'Rector suggested changes.' : 'Rector changed the file.',
                'diff' => $artifact['diff'],
                'applied_rectors' => $artifact['applied_rectors'],
            ];
        }

        foreach ($errors as $error) {
            $items[] = [
                'type' => 'error',
                'file' => $this->normalizePath((string) ($error['file'] ?? '')),
                'message' => (string) ($error['message'] ?? ''),
                'line' => (int) ($error['line'] ?? 0),
                'caused_by' => (string) ($error['caused_by'] ?? ''),
            ];
        }

        $totals = is_array($decoded['totals'] ?? null) ? $decoded['totals'] : [];
        $changedFiles = (int) ($totals['changed_files'] ?? count($fileDiffs));
        $errorCount = (int) ($totals['errors'] ?? count($errors));
        $status = 'passed';

        if ($errorCount > 0) {
            $status = 'error';
        } elseif ($changedFiles > 0) {
            $status = $dryRun ? 'failed' : 'changed';
        }

        return new NormalizedResult(
            tool: $this->name(),
            status: $status,
            summary: [
                'changed_files' => $changedFiles,
                'errors' => $errorCount,
            ],
            items: $items,
            artifacts: $artifacts,
            meta: [
                'exit_code' => $executionResult->exitCode,
                'duration' => $executionResult->duration,
                'command' => $preparedCommand->command,
                'dry_run' => $dryRun,
            ],
        );
    }

    /**
     * @param  list<string>  $arguments
     */
    private function command(array $arguments): string
    {
        $command = $arguments[0] ?? 'process';

        if ($command === '' || str_starts_with($command, '-')) {
            return 'process';
        }

        return $command;
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
