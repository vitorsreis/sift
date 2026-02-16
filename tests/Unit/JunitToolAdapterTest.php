<?php

declare(strict_types=1);

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Runtime\ToolLocator;
use Sift\Tools\ParatestToolAdapter;
use Sift\Tools\PestToolAdapter;
use Sift\Tools\PhpunitToolAdapter;

dataset('junit_tool_adapters', [
    'pest' => [PestToolAdapter::class, 'vendor/bin/pest'],
    'phpunit' => [PhpunitToolAdapter::class, 'vendor/bin/phpunit'],
    'paratest' => [ParatestToolAdapter::class, 'vendor/bin/paratest'],
]);

it('injects junit logging for junit based adapters', function (string $adapterClass, string $binary): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, basename($binary), "<?php\n");
        $adapter = new $adapterClass(new ToolLocator(PHP_BINARY));
        $prepared = $adapter->prepare($cwd, ['--filter', 'FocusedTest'], [
            'tool_binary' => $binary,
            'has_filter' => true,
            'has_coverage' => false,
        ]);

        expect($prepared->command[0] ?? null)->toBe(PHP_BINARY)
            ->and(str_replace('\\', '/', (string) ($prepared->command[1] ?? '')))->toContain($binary)
            ->and($prepared->command)->toContain('--log-junit')
            ->and(is_string($prepared->metadata['junit'] ?? null))->toBeTrue()
            ->and(($prepared->metadata['temp_files'] ?? []))->toHaveCount(1);
    } finally {
        removeDirectory($cwd);
    }
})->with('junit_tool_adapters');

it('normalizes junit failures and skips for junit based adapters', function (string $adapterClass, string $binary): void {
    $directory = makeTempDirectory('sift-junit-adapter-');

    try {
        $junitPath = $directory.DIRECTORY_SEPARATOR.'result.xml';
        file_put_contents($junitPath, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="default">
    <testcase name="it passes" class="Tests\PassingTest" />
    <testcase name="it fails" class="Tests\FailingTest">
      <failure message="Expected true but received false">details</failure>
    </testcase>
    <testcase name="it skips" class="Tests\SkippedTest">
      <skipped message="Not applicable" />
    </testcase>
  </testsuite>
</testsuites>
XML);

        $adapter = new $adapterClass(new ToolLocator(PHP_BINARY));
        $result = $adapter->parse(
            new ExecutionResult(
                exitCode: 1,
                stdout: '',
                stderr: '',
                duration: 25,
            ),
            new PreparedCommand(
                command: [PHP_BINARY, $binary, '--filter', 'FocusedTest'],
                cwd: siftRoot(),
                metadata: ['junit' => $junitPath],
            ),
            [
                'has_filter' => true,
                'has_coverage' => true,
            ],
        );

        expect($result->status)->toBe('failed')
            ->and($result->summary)->toBe([
                'tests' => 3,
                'passed' => 1,
                'failures' => 1,
                'errors' => 0,
                'skipped' => 1,
            ])
            ->and($result->items)->toHaveCount(2)
            ->and($result->items[0]['type'])->toBe('failure')
            ->and($result->items[0]['test'])->toBe('it fails')
            ->and($result->items[0]['file'])->toBe('Tests/FailingTest')
            ->and($result->items[1]['type'])->toBe('skipped')
            ->and($result->meta['exit_code'])->toBe(1)
            ->and($result->meta['duration'])->toBe(25)
            ->and($result->meta['filter'])->toBeTrue()
            ->and($result->meta['coverage'])->toBeTrue();
    } finally {
        removeDirectory($directory);
    }
})->with('junit_tool_adapters');
