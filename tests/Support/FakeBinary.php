<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final readonly class FakeBinary
{
    private function __construct(
        private string $binary,
        private string $argvFile,
    ) {}

    /**
     * @param array<string, string> $writes
     */
    public static function create(
        FixtureProject $project,
        string $name,
        string $stdout = '',
        string $stderr = '',
        int $exitCode = 0,
        int $delayMs = 0,
        array $writes = [],
    ): self {
        $base = 'build/fake-binaries/' . $name;
        $argvFile = $project->path($base . '-argv.json');
        $script = $project->write($base . '.php', self::script([
            'argv_file' => $argvFile,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $exitCode,
            'delay_ms' => $delayMs,
            'writes' => $writes,
        ]));

        if (PHP_OS_FAMILY === 'Windows') {
            $binary = $project->write(
                $base . '.bat',
                sprintf("@echo off\r\n\"%s\" \"%s\" %%*\r\nexit /b %%ERRORLEVEL%%\r\n", PHP_BINARY, $script),
            );
        } else {
            $binary = $project->write(
                $base,
                sprintf("#!/usr/bin/env sh\nexec %s %s \"$@\"\n", escapeshellarg(PHP_BINARY), escapeshellarg($script)),
            );

            chmod($binary, 0755);
        }

        return new self($binary, $argvFile);
    }

    public function binary(): string
    {
        return $this->binary;
    }

    /**
     * @return list<string>
     */
    public function argv(): array
    {
        if (! is_file($this->argvFile)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->argvFile), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new RuntimeException('Fake binary argv capture must be a list.');
        }

        $arguments = [];

        foreach ($decoded as $argument) {
            if (! is_string($argument)) {
                throw new RuntimeException('Fake binary argv capture must contain only strings.');
            }

            $arguments[] = $argument;
        }

        return $arguments;
    }

    /**
     * @param array{argv_file: string, stdout: string, stderr: string, exit_code: int, delay_ms: int, writes: array<string, string>} $config
     */
    private static function script(array $config): string
    {
        return sprintf(
            <<<'PHP_WRAP'
            <?php

            declare(strict_types=1);

            $config = %s;
            $arguments = array_slice($_SERVER['argv'] ?? [], 1);

            file_put_contents($config['argv_file'], json_encode($arguments, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

            if ($config['delay_ms'] > 0) {
                usleep($config['delay_ms'] * 1000);
            }

            foreach ($config['writes'] as $option => $contents) {
                $path = fake_binary_option_value($arguments, $option);

                if ($path === null || $path === '') {
                    continue;
                }

                $directory = dirname($path);

                if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                    fwrite(STDERR, sprintf('Could not create directory "%%s".', $directory));
                    exit(70);
                }

                file_put_contents($path, $contents);
            }

            if ($config['stdout'] !== '') {
                fwrite(STDOUT, $config['stdout']);
            }

            if ($config['stderr'] !== '') {
                fwrite(STDERR, $config['stderr']);
            }

            exit($config['exit_code']);

            /**
             * @param list<string> $arguments
             */
            function fake_binary_option_value(array $arguments, string $option): ?string
            {
                foreach ($arguments as $index => $argument) {
                    if (str_starts_with($argument, $option . '=')) {
                        return substr($argument, strlen($option) + 1);
                    }

                    if ($argument === $option) {
                        return $arguments[$index + 1] ?? null;
                    }
                }

                return null;
            }
            PHP_WRAP,
            var_export($config, true),
        );
    }
}
