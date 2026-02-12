<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

function siftRoot(): string
{
    return dirname(__DIR__);
}

function makeTempDirectory(string $prefix = 'sift-test-'): string
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.bin2hex(random_bytes(8));

    if (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException(sprintf('Unable to create temp directory: %s', $directory));
    }

    return $directory;
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $items = scandir($directory);

    if (! is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory.DIRECTORY_SEPARATOR.$item;

        if (is_dir($path)) {
            removeDirectory($path);

            continue;
        }

        @unlink($path);
    }

    @rmdir($directory);
}

/**
 * @param  array<string, mixed>  $payload
 */
function writeJsonFile(string $path, array $payload): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException(sprintf('Unable to create directory: %s', $directory));
    }

    file_put_contents(
        $path,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    );
}

/**
 * @param  array<string, mixed>  $config
 */
function writeSiftConfig(string $cwd, array $config, string $filename = 'sift.json'): string
{
    $path = $cwd.DIRECTORY_SEPARATOR.$filename;
    writeJsonFile($path, $config);

    return $path;
}

function createProjectTool(string $cwd, string $tool, string $contents): string
{
    $directory = $cwd.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin';

    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException(sprintf('Unable to create tool directory: %s', $directory));
    }

    $path = $directory.DIRECTORY_SEPARATOR.$tool;
    file_put_contents($path, $contents);

    return $path;
}

function createProxyToolBinary(string $cwd, string $tool, string $target): string
{
    $targetExport = var_export($target, true);

    return createProjectTool($cwd, $tool, <<<PHP
<?php

declare(strict_types=1);

\$target = {$targetExport};
\$arguments = array_slice(\$_SERVER['argv'] ?? [], 1);
\$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(\$target);

foreach (\$arguments as \$argument) {
    \$command .= ' '.escapeshellarg((string) \$argument);
}

passthru(\$command, \$exitCode);

exit(\$exitCode);
PHP);
}

function createPestProject(string $cwd): void
{
    $testsDirectory = $cwd.DIRECTORY_SEPARATOR.'tests';

    if (! is_dir($testsDirectory) && ! mkdir($testsDirectory, 0777, true) && ! is_dir($testsDirectory)) {
        throw new RuntimeException(sprintf('Unable to create tests directory: %s', $testsDirectory));
    }

    file_put_contents($cwd.DIRECTORY_SEPARATOR.'phpunit.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit colors="true">
  <testsuites>
    <testsuite name="default">
      <directory>tests</directory>
    </testsuite>
  </testsuites>
</phpunit>
XML);

    file_put_contents($testsDirectory.DIRECTORY_SEPARATOR.'Pest.php', <<<'PHP'
<?php

declare(strict_types=1);
PHP);

    file_put_contents($testsDirectory.DIRECTORY_SEPARATOR.'PassingTest.php', <<<'PHP'
<?php

declare(strict_types=1);

it('passes', function (): void {
    expect(true)->toBeTrue();
});
PHP);

    file_put_contents($testsDirectory.DIRECTORY_SEPARATOR.'FailingTest.php', <<<'PHP'
<?php

declare(strict_types=1);

it('fails', function (): void {
    expect(false)->toBeTrue();
});
PHP);
}

/**
 * @param  list<string>  $arguments
 */
function runSift(array $arguments, ?string $cwd = null): Process
{
    $process = new Process(
        command: [PHP_BINARY, siftRoot().DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'sift', ...$arguments],
        cwd: $cwd ?? siftRoot(),
    );

    $process->run();

    return $process;
}

/**
 * @return array<string, mixed>
 */
function decodeJsonOutput(Process $process): array
{
    $raw = trim($process->getOutput() !== '' ? $process->getOutput() : $process->getErrorOutput());

    if ($raw === '') {
        throw new RuntimeException('The process did not produce JSON output.');
    }

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode(str_replace("\r", '', $raw), true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
}
