<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final readonly class FixtureProject
{
    private function __construct(
        private string $root,
    ) {}

    public static function create(string $prefix = 'sift-test-'): self
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));

        if (! mkdir($root, 0777, true) && ! is_dir($root)) {
            throw new RuntimeException(sprintf('Could not create fixture project "%s".', $root));
        }

        $canonicalRoot = realpath($root);

        if ($canonicalRoot === false) {
            throw new RuntimeException(sprintf('Could not resolve fixture project "%s".', $root));
        }

        return new self($canonicalRoot);
    }

    public function root(): string
    {
        return $this->root;
    }

    public function path(string ...$parts): string
    {
        return implode(DIRECTORY_SEPARATOR, [
            $this->root,
            ...array_map(
                static fn(string $part): string => trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part), DIRECTORY_SEPARATOR),
                $parts,
            ),
        ]);
    }

    public function mkdir(string $path): string
    {
        $directory = $this->path($path);

        if (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create fixture directory "%s".', $directory));
        }

        return $directory;
    }

    public function write(string $path, string $contents): string
    {
        $file = $this->path($path);
        $directory = dirname($file);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create fixture directory "%s".', $directory));
        }

        if (file_put_contents($file, $contents) === false) {
            throw new RuntimeException(sprintf('Could not write fixture file "%s".', $file));
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $document
     */
    public function writeJson(string $path, array $document): string
    {
        return $this->write($path, json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    /**
     * @return array<string, mixed>
     */
    public function readJson(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($this->path($path)), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('Fixture JSON root must be an object.');
        }

        $normalized = [];

        foreach ($decoded as $key => $value) {
            if (! is_string($key)) {
                throw new RuntimeException('Fixture JSON root must use string keys.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
