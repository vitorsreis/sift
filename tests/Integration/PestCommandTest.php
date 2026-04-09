<?php

declare(strict_types=1);

it('normalizes passing pest executions', function (): void {
    $cwd = makeTempDirectory();

    try {
        createPestProject($cwd);
        createProxyToolBinary($cwd, 'pest', siftRoot().DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest');

        $process = runSift([
            '--no-history',
            '--size=fuller',
            '--format=json',
            '--pretty',
            'pest',
            '--configuration',
            'phpunit.xml',
            'tests/PassingTest.php',
        ], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['tool'])->toBe('pest')
            ->and($payload['status'])->toBe('passed')
            ->and($payload['summary'])->toBe([
                'tests' => 1,
                'passed' => 1,
                'failures' => 0,
                'errors' => 0,
                'skipped' => 0,
            ])
            ->and($payload['meta']['filter'])->toBeFalse()
            ->and($payload['meta']['coverage'])->toBeFalse()
            ->and($payload['meta']['exit_code'])->toBe(0)
            ->and($payload['meta']['duration'])->toBeInt()
            ->and($payload['meta']['created_at'])->toBeString();
    } finally {
        removeDirectory($cwd);
    }
});

it('normalizes failing pest executions with testcase details', function (): void {
    $cwd = makeTempDirectory();

    try {
        createPestProject($cwd);
        createProxyToolBinary($cwd, 'pest', siftRoot().DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest');

        $process = runSift([
            '--no-history',
            '--size=fuller',
            '--format=json',
            '--pretty',
            'pest',
            '--configuration',
            'phpunit.xml',
            'tests/FailingTest.php',
        ], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['tool'])->toBe('pest')
            ->and($payload['status'])->toBe('failed')
            ->and($payload['summary']['tests'])->toBe(1)
            ->and($payload['summary']['failures'])->toBe(1)
            ->and($payload['items'])->toHaveCount(1)
            ->and($payload['items'][0]['type'])->toBe('failure')
            ->and($payload['items'][0]['test'])->toBe('it fails')
            ->and(str_replace('\\', '/', (string) $payload['items'][0]['file']))->toContain('tests/FailingTest.php')
            ->and($payload['items'][0]['message'])->toContain('Failed asserting that false is true.')
            ->and($payload['items'][0]['message'])->not->toContain('tests/FailingTest.php:6')
            ->and($payload['items'][0]['line'])->toBe(6)
            ->and($payload['meta']['exit_code'])->toBe(1)
            ->and($payload['meta']['duration'])->toBeInt()
            ->and($payload['meta']['created_at'])->toBeString();
    } finally {
        removeDirectory($cwd);
    }
});

it('surfaces coverage threshold failures from pest in compact and normal output', function (): void {
    $cwd = makeTempDirectory();

    try {
        createCoveragePestBinary($cwd, 72.5, 80.0, [
            ['file' => 'src/LowCoverage.php', 'covered' => 5, 'statements' => 10],
            ['file' => 'src/GoodCoverage.php', 'covered' => 9, 'statements' => 10],
        ]);

        $compact = runSift([
            '--no-history',
            '--size=compact',
            '--format=json',
            'pest',
            '--coverage',
            '--min=80',
        ], $cwd);
        $normal = runSift([
            '--no-history',
            '--size=normal',
            '--format=json',
            'pest',
            '--coverage',
            '--min=80',
        ], $cwd);

        expect($compact->getExitCode())->toBe(0)
            ->and($normal->getExitCode())->toBe(0);

        $compactPayload = decodeJsonOutput($compact);
        $normalPayload = decodeJsonOutput($normal);

        expect($compactPayload)->toMatchArray([
            'status' => 'failed',
            'coverage_percent' => 72.5,
            'coverage_min' => 80.0,
            'coverage_files_below_min' => 1,
        ]);

        expect($normalPayload['status'])->toBe('failed')
            ->and($normalPayload['summary'])->toMatchArray([
                'coverage_percent' => 72.5,
                'coverage_min' => 80.0,
                'coverage_files_below_min' => 1,
            ])
            ->and($normalPayload['items'])->toMatchArray([
                [
                    'type' => 'coverage',
                    'file' => 'src/LowCoverage.php',
                    'percent' => 50,
                ],
            ]);
    } finally {
        removeDirectory($cwd);
    }
});

/**
 * @param  list<array{file: string, covered: int, statements: int}>  $files
 */
function createCoveragePestBinary(string $cwd, float $coveragePercent, float $minimum, array $files): void
{
    $totalStatements = 40;
    $totalCovered = (int) round(($coveragePercent / 100) * $totalStatements);
    $fileEntries = '';

    foreach ($files as $entry) {
        $fileEntries .= sprintf(
            "    <file name=\"%s/%s\">\n      <metrics statements=\"%d\" coveredstatements=\"%d\" />\n    </file>\n",
            str_replace('\\', '/', $cwd),
            $entry['file'],
            $entry['statements'],
            $entry['covered'],
        );
    }

    $junit = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite tests="1" failures="0" errors="0" skipped="0">
    <testcase class="Tests\PassingTest" name="it passes" file="tests/PassingTest.php::it passes" />
  </testsuite>
</testsuites>
XML;
    $clover = sprintf(
        "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<coverage generated=\"1234567890\">\n  <project timestamp=\"1234567890\">\n    <metrics statements=\"%d\" coveredstatements=\"%d\" />\n%s  </project>\n</coverage>\n",
        $totalStatements,
        $totalCovered,
        $fileEntries,
    );
    $junitExport = var_export($junit, true);
    $cloverExport = var_export($clover, true);
    $minimumExport = var_export($minimum, true);
    $coveragePercentExport = var_export($coveragePercent, true);

    createProjectTool($cwd, 'pest', <<<PHP
<?php

declare(strict_types=1);

\$arguments = array_slice(\$_SERVER['argv'] ?? [], 1);
\$junit = null;
\$clover = null;

foreach (\$arguments as \$index => \$argument) {
    if (\$argument === '--log-junit' && isset(\$arguments[\$index + 1])) {
        \$junit = \$arguments[\$index + 1];
        continue;
    }

    if (\$argument === '--coverage-clover' && isset(\$arguments[\$index + 1])) {
        \$clover = \$arguments[\$index + 1];
        continue;
    }

    if (str_starts_with(\$argument, '--log-junit=')) {
        \$junit = substr(\$argument, strlen('--log-junit='));
        continue;
    }

    if (str_starts_with(\$argument, '--coverage-clover=')) {
        \$clover = substr(\$argument, strlen('--coverage-clover='));
    }
}

if (! is_string(\$junit) || \$junit === '' || ! is_string(\$clover) || \$clover === '') {
    fwrite(STDERR, "missing junit or clover path\\n");
    exit(9);
}

file_put_contents(\$junit, $junitExport);
file_put_contents(\$clover, $cloverExport);
fwrite(STDOUT, sprintf("Coverage %.1f%% (minimum %.1f%%)\\n", $coveragePercentExport, $minimumExport));

exit(1);
PHP);
}
