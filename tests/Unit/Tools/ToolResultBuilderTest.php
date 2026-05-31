<?php

declare(strict_types=1);

use Sift\Core\Clock;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Tools\ToolContext;
use Sift\Tools\ToolResultBuilder;

it('adds common metadata to parsed tool results', function (): void {
    $clock = new class implements Clock {
        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable('2026-05-31T12:00:00+00:00');
        }

        public function monotonicSeconds(): float
        {
            return 123.0;
        }
    };

    $result = (new ToolResultBuilder($clock))->build(
        parsed: new NormalizedResult(
            tool: 'pest',
            status: RunStatus::Failed,
            summary: ['tests' => 12, 'failures' => 1],
            items: [['type' => 'test_failure', 'message' => 'Failed.']],
            artifacts: [['type' => 'junit', 'path' => 'build/junit.xml']],
            extra: ['native_exit_code' => 1],
            meta: ['adapter' => 'pest'],
        ),
        execution: ExecutionResult::completed(
            exitCode: 1,
            stdout: '',
            stderr: '',
            durationSeconds: 0.125,
        ),
        command: new PreparedCommand(
            tool: 'pest',
            binary: 'vendor/bin/pest',
            arguments: ['--filter', 'CheckoutTest'],
            cwd: '/project',
        ),
        context: new ToolContext(
            toolName: 'pest',
            subcommand: 'test',
            userArgs: ['--filter', 'CheckoutTest'],
            cwd: '/project',
            dryRun: true,
            filter: 'CheckoutTest',
            coverage: true,
            coverageMin: 80.0,
            mode: 'test',
            warnings: ['JUnit output was injected.'],
        ),
    );

    expect($result->toPayload())->toBe([
        'tool' => 'pest',
        'status' => 'failed',
        'summary' => ['tests' => 12, 'failures' => 1],
        'items' => [['type' => 'test_failure', 'message' => 'Failed.']],
        'artifacts' => [['type' => 'junit', 'path' => 'build/junit.xml']],
        'extra' => ['native_exit_code' => 1],
        'meta' => [
            'adapter' => 'pest',
            'exit_code' => 1,
            'duration' => 0.125,
            'created_at' => '2026-05-31T12:00:00+00:00',
            'command' => ['vendor/bin/pest', '--filter', 'CheckoutTest'],
            'filter' => 'CheckoutTest',
            'coverage' => true,
            'coverage_min' => 80.0,
            'mode' => 'test',
            'dry_run' => true,
            'subcommand' => 'test',
            'warnings' => ['JUnit output was injected.'],
        ],
    ]);
});

it('marks repair mode in metadata only when requested', function (): void {
    $result = (new ToolResultBuilder())->build(
        parsed: NormalizedResult::passed('pint'),
        execution: ExecutionResult::completed(
            exitCode: 0,
            stdout: '',
            stderr: '',
            durationSeconds: 0.01,
        ),
        command: new PreparedCommand(
            tool: 'pint',
            binary: 'vendor/bin/pint',
            arguments: ['--repair'],
            cwd: '/project',
        ),
        context: new ToolContext(
            toolName: 'pint',
            cwd: '/project',
            repair: true,
        ),
    );

    expect($result->toPayload()['meta'])->toHaveKey('repair', true);
});
