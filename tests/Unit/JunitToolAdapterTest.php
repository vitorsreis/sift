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

it('preserves user supplied junit report paths for junit based adapters', function (string $adapterClass, string $binary): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, basename($binary), "<?php\n");
        $adapter = new $adapterClass(new ToolLocator(PHP_BINARY));
        $prepared = $adapter->prepare($cwd, ['--log-junit', 'build/custom-report.xml'], [
            'tool_binary' => $binary,
            'has_filter' => false,
            'has_coverage' => false,
        ]);

        expect($prepared->metadata['junit'] ?? null)->toBe('build/custom-report.xml')
            ->and($prepared->metadata['temp_files'] ?? [])->toBe([]);
    } finally {
        removeDirectory($cwd);
    }
})->with('junit_tool_adapters');

it('injects clover logging for pest when coverage minimum is requested', function (): void {
    $cwd = makeTempDirectory('sift-pest-prepare-');

    try {
        createProjectTool($cwd, 'pest', "<?php\n");
        $adapter = new PestToolAdapter(new ToolLocator(PHP_BINARY));
        $prepared = $adapter->prepare($cwd, ['--coverage', '--min=80'], [
            'tool_binary' => 'vendor/bin/pest',
            'has_filter' => false,
            'has_coverage' => true,
            'coverage_min' => 80.0,
        ]);

        expect($prepared->command)->toContain('--log-junit', '--coverage-clover')
            ->and(is_string($prepared->metadata['coverage_clover'] ?? null))->toBeTrue()
            ->and(($prepared->metadata['temp_files'] ?? []))->toHaveCount(2);
    } finally {
        removeDirectory($cwd);
    }
});

it('normalizes junit failures and skips for junit based adapters', function (string $adapterClass, string $binary): void {
    $directory = makeTempDirectory('sift-junit-adapter-');

    try {
        $junitPath = $directory.DIRECTORY_SEPARATOR.'result.xml';
        file_put_contents($junitPath, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="default">
    <testcase name="it passes" class="Tests\PassingTest" file="tests/PassingTest.php::it passes" />
    <testcase name="it fails" class="Tests\FailingTest" file="tests/FailingTest.php::it fails">
      <failure message="Expected true but received false">Failed asserting that false is true.
at tests/FailingTest.php:16</failure>
    </testcase>
    <testcase name="it skips" class="Tests\SkippedTest" file="tests/SkippedTest.php::it skips">
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
            ->and($result->items[0]['file'])->toBe('tests/FailingTest.php')
            ->and($result->items[0]['line'])->toBe(16)
            ->and($result->items[1]['type'])->toBe('skipped')
            ->and($result->items[1]['file'])->toBe('tests/SkippedTest.php')
            ->and($result->meta['exit_code'])->toBe(1)
            ->and($result->meta['duration'])->toBe(25)
            ->and($result->meta['filter'])->toBeTrue()
            ->and($result->meta['coverage'])->toBeTrue();
    } finally {
        removeDirectory($directory);
    }
})->with('junit_tool_adapters');

it('surfaces coverage threshold results for pest junit parsing', function (): void {
    $directory = makeTempDirectory('sift-pest-coverage-');

    try {
        $junitPath = $directory.DIRECTORY_SEPARATOR.'result.xml';
        $coveragePath = $directory.DIRECTORY_SEPARATOR.'clover.xml';

        file_put_contents($junitPath, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="default">
    <testcase name="it passes" class="Tests\PassingTest" file="tests/PassingTest.php::it passes" />
  </testsuite>
</testsuites>
XML);

        file_put_contents($coveragePath, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1234567890">
  <project timestamp="1234567890">
    <metrics statements="10" coveredstatements="7" />
    <file name="/project/src/UnderCovered.php">
      <metrics statements="10" coveredstatements="6" />
    </file>
    <file name="/project/src/FullyCovered.php">
      <metrics statements="5" coveredstatements="5" />
    </file>
  </project>
</coverage>
XML);

        $adapter = new PestToolAdapter(new ToolLocator(PHP_BINARY));
        $result = $adapter->parse(
            new ExecutionResult(
                exitCode: 1,
                stdout: '',
                stderr: '',
                duration: 42,
            ),
            new PreparedCommand(
                command: [PHP_BINARY, 'vendor/bin/pest', '--coverage', '--min=80'],
                cwd: '/project',
                metadata: [
                    'junit' => $junitPath,
                    'coverage_clover' => $coveragePath,
                ],
            ),
            [
                'has_filter' => false,
                'has_coverage' => true,
                'coverage_min' => 80.0,
            ],
        );

        expect($result->status)->toBe('failed')
            ->and($result->summary['coverage_percent'])->toBe(70.0)
            ->and($result->summary['coverage_min'])->toBe(80.0)
            ->and($result->summary['coverage_files_below_min'])->toBe(1)
            ->and($result->items)->toHaveCount(1)
            ->and($result->items[0]['type'])->toBe('coverage')
            ->and($result->items[0]['file'])->toBe('src/UnderCovered.php')
            ->and($result->items[0]['percent'])->toBe(60.0)
            ->and($result->extra)->toBe([])
            ->and($result->meta['coverage'])->toBeTrue()
            ->and($result->meta['coverage_min'])->toBe(80.0);
    } finally {
        removeDirectory($directory);
    }
});
