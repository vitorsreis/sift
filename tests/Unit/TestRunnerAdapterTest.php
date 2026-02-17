<?php

declare(strict_types=1);

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;
use Sift\Tools\ParatestToolAdapter;
use Sift\Tools\PestToolAdapter;
use Sift\Tools\PhpunitToolAdapter;

dataset('junit_preparable_adapters', [
    'pest' => [PestToolAdapter::class, 'pest', 'vendor/bin/pest', '--coverage'],
    'phpunit' => [PhpunitToolAdapter::class, 'phpunit', 'vendor/bin/phpunit', '--coverage-clover'],
    'paratest' => [ParatestToolAdapter::class, 'paratest', 'vendor/bin/paratest', '--coverage'],
]);

it('detects filter and coverage context for test runner adapters', function (string $adapterClass, string $tool, string $binary, string $coverageFlag): void {
    $adapter = new $adapterClass(new ToolLocator(PHP_BINARY));
    $arguments = ['--filter', 'FocusedTest', $coverageFlag];

    expect($adapter->detectContext($arguments))->toMatchArray([
        'arguments' => $arguments,
        'has_filter' => true,
        'has_coverage' => true,
    ]);
})->with('junit_preparable_adapters');

it('reuses explicit junit paths without duplicating them for phpunit and paratest', function (string $adapterClass, string $tool, string $binary): void {
    $cwd = makeTempDirectory('sift-junit-explicit-');
    $junitPath = $cwd.DIRECTORY_SEPARATOR.'reports'.DIRECTORY_SEPARATOR.$tool.'.xml';

    try {
        createProjectTool($cwd, $tool, "<?php\n");
        $adapter = new $adapterClass(new ToolLocator(PHP_BINARY));

        $prepared = $adapter->prepare($cwd, ['--log-junit', $junitPath], [
            'tool_binary' => $binary,
            'has_filter' => false,
            'has_coverage' => false,
        ]);

        expect(array_count_values($prepared->command)['--log-junit'] ?? 0)->toBe(1)
            ->and($prepared->metadata['junit'])->toBe($junitPath)
            ->and($prepared->metadata['temp_files'])->toBe([$junitPath]);
    } finally {
        removeDirectory($cwd);
    }
})->with([
    'phpunit' => [PhpunitToolAdapter::class, 'phpunit', 'vendor/bin/phpunit'],
    'paratest' => [ParatestToolAdapter::class, 'paratest', 'vendor/bin/paratest'],
]);

it('reuses explicit junit and coverage clover paths for pest', function (): void {
    $cwd = makeTempDirectory('sift-pest-explicit-');
    $junitPath = $cwd.DIRECTORY_SEPARATOR.'reports'.DIRECTORY_SEPARATOR.'pest.xml';
    $coveragePath = $cwd.DIRECTORY_SEPARATOR.'reports'.DIRECTORY_SEPARATOR.'clover.xml';

    try {
        createProjectTool($cwd, 'pest', "<?php\n");
        $adapter = new PestToolAdapter(new ToolLocator(PHP_BINARY));

        $prepared = $adapter->prepare($cwd, [
            '--log-junit', $junitPath,
            '--coverage-clover', $coveragePath,
            '--coverage',
        ], [
            'tool_binary' => 'vendor/bin/pest',
            'has_filter' => false,
            'has_coverage' => true,
            'coverage_min' => null,
        ]);

        expect(array_count_values($prepared->command)['--log-junit'] ?? 0)->toBe(1)
            ->and(array_count_values($prepared->command)['--coverage-clover'] ?? 0)->toBe(1)
            ->and($prepared->metadata['junit'])->toBe($junitPath)
            ->and($prepared->metadata['coverage_clover'])->toBe($coveragePath)
            ->and($prepared->metadata['temp_files'])->toBe([]);
    } finally {
        removeDirectory($cwd);
    }
});

it('fails parsing when junit output is missing for test runner adapters', function (string $adapterClass, string $tool, string $binary): void {
    $adapter = new $adapterClass(new ToolLocator(PHP_BINARY));

    expect(fn () => $adapter->parse(
        new ExecutionResult(1, '', '', 4),
        new PreparedCommand([PHP_BINARY, $binary], siftRoot(), metadata: ['junit' => siftRoot().DIRECTORY_SEPARATOR.'missing.xml']),
        [],
    ))->toThrow(UserFacingException::class);
})->with([
    'pest' => [PestToolAdapter::class, 'pest', 'vendor/bin/pest'],
    'phpunit' => [PhpunitToolAdapter::class, 'phpunit', 'vendor/bin/phpunit'],
    'paratest' => [ParatestToolAdapter::class, 'paratest', 'vendor/bin/paratest'],
]);
