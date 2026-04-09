<?php

declare(strict_types=1);

it('normalizes passing paratest executions with filter and coverage context', function (): void {
    $cwd = makeTempDirectory();

    try {
        createFakeParatestBinary($cwd, false);

        $process = runSift([
            '--no-history',
            '--size=fuller',
            '--format=json',
            '--pretty',
            'paratest',
            '--filter=Smoke',
            '--coverage-text',
            'tests/PassingTest.php',
        ], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['tool'])->toBe('paratest')
            ->and($payload['status'])->toBe('passed')
            ->and($payload['summary'])->toBe([
                'tests' => 1,
                'passed' => 1,
                'failures' => 0,
                'errors' => 0,
                'skipped' => 0,
            ])
            ->and($payload['meta']['filter'])->toBeTrue()
            ->and($payload['meta']['coverage'])->toBeTrue()
            ->and($payload['meta']['command'])->toContain('--log-junit')
            ->and($payload['meta']['exit_code'])->toBe(0)
            ->and($payload['meta']['duration'])->toBeInt()
            ->and($payload['meta']['created_at'])->toBeString();
    } finally {
        removeDirectory($cwd);
    }
});

it('normalizes failing paratest executions with testcase details', function (): void {
    $cwd = makeTempDirectory();

    try {
        createFakeParatestBinary($cwd, true);

        $process = runSift([
            '--no-history',
            '--size=fuller',
            '--format=json',
            '--pretty',
            'paratest',
            'tests/FailingTest.php',
        ], $cwd);

        expect($process->getExitCode())->toBe(0);

        $payload = decodeJsonOutput($process);

        expect($payload['tool'])->toBe('paratest')
            ->and($payload['status'])->toBe('failed')
            ->and($payload['summary']['tests'])->toBe(1)
            ->and($payload['summary']['failures'])->toBe(1)
            ->and($payload['items'])->toHaveCount(1)
            ->and($payload['items'][0]['type'])->toBe('failure')
            ->and($payload['items'][0]['test'])->toBe('it fails in parallel')
            ->and($payload['items'][0]['file'])->toBe('tests/FailingTest.php')
            ->and($payload['items'][0]['message'])->toContain('Failed asserting that false is true.')
            ->and($payload['items'][0]['message'])->not->toContain('tests/FailingTest.php:12')
            ->and($payload['items'][0]['line'])->toBe(12)
            ->and($payload['meta']['filter'])->toBeFalse()
            ->and($payload['meta']['coverage'])->toBeFalse()
            ->and($payload['meta']['exit_code'])->toBe(1)
            ->and($payload['meta']['duration'])->toBeInt()
            ->and($payload['meta']['created_at'])->toBeString();
    } finally {
        removeDirectory($cwd);
    }
});

function createFakeParatestBinary(string $cwd, bool $failing): void
{
    $xml = $failing
        ? <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite tests="1" failures="1" errors="0" skipped="0">
    <testcase class="Tests\Feature\ParallelSuite" name="it fails in parallel" file="tests/FailingTest.php::it fails in parallel">
      <failure message="Expected true, got false">Failed asserting that false is true.
at tests/FailingTest.php:12</failure>
    </testcase>
  </testsuite>
</testsuites>
XML
        : <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite tests="1" failures="0" errors="0" skipped="0">
    <testcase class="Tests\Feature\ParallelSuite" name="it passes in parallel" />
  </testsuite>
</testsuites>
XML;

    $xmlExport = var_export($xml, true);
    $exitCode = $failing ? 1 : 0;

    createProjectTool($cwd, 'paratest', <<<PHP
<?php

declare(strict_types=1);

\$arguments = array_slice(\$_SERVER['argv'] ?? [], 1);
\$junit = null;

foreach (\$arguments as \$index => \$argument) {
    if (\$argument === '--log-junit' && isset(\$arguments[\$index + 1])) {
        \$junit = \$arguments[\$index + 1];
        break;
    }

    if (str_starts_with(\$argument, '--log-junit=')) {
        \$junit = substr(\$argument, strlen('--log-junit='));
        break;
    }
}

if (! is_string(\$junit) || \$junit === '') {
    fwrite(STDERR, "missing junit path\\n");
    exit(9);
}

file_put_contents(\$junit, $xmlExport);

exit($exitCode);
PHP);
}
