<?php

declare(strict_types=1);

namespace Sift\Execution;

final readonly class PhpRuntimeArguments
{
    /**
     * @param list<string>|null $tokens
     */
    public function __construct(
        private ?array $tokens = null,
    ) {}

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->extractDefinitions($this->tokens ?? $this->currentProcessTokens());
    }

    /**
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    private function extractDefinitions(array $tokens): array
    {
        array_shift($tokens);
        $arguments = [];
        $counter = count($tokens);

        for ($index = 0; $index < $counter; ++$index) {
            $token = $tokens[$index];

            if ($token === '-d') {
                $value = $tokens[$index + 1] ?? null;

                if (is_string($value) && $value !== '') {
                    $arguments[] = '-d' . $value;
                    ++$index;
                }

                continue;
            }

            if (str_starts_with($token, '-d') && strlen($token) > 2) {
                $arguments[] = $token;
                continue;
            }

            if (str_starts_with($token, '-')) {
                continue;
            }

            break;
        }

        return $arguments;
    }

    /**
     * @return list<string>
     */
    private function currentProcessTokens(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->windowsProcessTokens();
        }

        $cmdline = '/proc/' . getmypid() . '/cmdline';

        if (is_file($cmdline)) {
            $contents = file_get_contents($cmdline);

            if (is_string($contents) && $contents !== '') {
                return array_values(array_filter(explode("\0", $contents), static fn(string $token): bool => $token !== ''));
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function windowsProcessTokens(): array
    {
        $commandLine = $this->windowsCommandLine();

        return $commandLine === null ? [] : $this->tokenizeWindowsCommandLine($commandLine);
    }

    private function windowsCommandLine(): ?string
    {
        $process = @proc_open(
            [
                'powershell.exe',
                '-NoProfile',
                '-NonInteractive',
                '-Command',
                '(Get-CimInstance Win32_Process -Filter "ProcessId=' . getmypid() . '").CommandLine',
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (! is_resource($process)) {
            return null;
        }

        foreach ([0] as $pipe) {
            if (isset($pipes[$pipe]) && is_resource($pipes[$pipe])) {
                fclose($pipes[$pipe]);
            }
        }

        $output = isset($pipes[1]) && is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : false;

        foreach ([1, 2] as $pipe) {
            if (isset($pipes[$pipe]) && is_resource($pipes[$pipe])) {
                fclose($pipes[$pipe]);
            }
        }

        proc_close($process);

        if (! is_string($output)) {
            return null;
        }

        $output = trim($output);

        return $output === '' ? null : $output;
    }

    /**
     * @return list<string>
     */
    private function tokenizeWindowsCommandLine(string $commandLine): array
    {
        $tokens = [];
        $current = '';
        $inQuotes = false;
        $length = strlen($commandLine);

        for ($index = 0; $index < $length; ++$index) {
            $char = $commandLine[$index];

            if ($char === '"') {
                $inQuotes = ! $inQuotes;
                continue;
            }

            if (! $inQuotes && ctype_space($char)) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }

                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }
}
