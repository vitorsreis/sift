<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final readonly class CliRunner
{
    /**
     * @param list<string> $arguments
     *
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    public static function run(array $arguments, ?string $cwd = null): array
    {
        $root = dirname(__DIR__, 2);
        $command = [PHP_BINARY, $root . '/bin/sift'];

        foreach ($arguments as $argument) {
            $command[] = $argument;
        }

        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd ?? $root,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start Sift process.');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if ($stdout === false || $stderr === false) {
            throw new RuntimeException('Could not read Sift process output.');
        }

        return [
            'exit_code' => proc_close($process),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $json): array
    {
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload) || array_is_list($payload)) {
            throw new RuntimeException('Sift payload must be an object.');
        }

        return self::stringKeyedObject($payload);
    }

    /**
     * @param array<mixed, mixed> $object
     *
     * @return array<string, mixed>
     */
    private static function stringKeyedObject(array $object): array
    {
        $normalized = [];

        foreach ($object as $key => $value) {
            if (! is_string($key)) {
                throw new RuntimeException('Sift payload must use string keys.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
