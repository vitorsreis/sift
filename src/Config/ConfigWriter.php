<?php

declare(strict_types=1);

namespace Sift\Config;

use RuntimeException;

/**
 * @phpstan-type JsonObject array<string, mixed>
 */
final class ConfigWriter
{
    /**
     * @param JsonObject|null $existing
     */
    public function writeDefaults(string $path, ?array $existing = null): void
    {
        $this->write($path, $this->defaultsDocument($existing));
    }

    /**
     * @param JsonObject|null $existing
     *
     * @return JsonObject
     */
    public function defaultsDocument(?array $existing = null): array
    {
        $outputDefaults = ConfigDefaults::output();
        $historyDefaults = ConfigDefaults::history();

        if ($existing === null) {
            return ConfigDefaults::document();
        }

        return [
            '$schema' => ConfigDefaults::schemaUrl(),
            'output' => $this->mergeKnownObject($outputDefaults, $existing['output'] ?? null),
            'history' => $this->mergeKnownObject($historyDefaults, $existing['history'] ?? null),
            'tools' => is_array($existing['tools'] ?? null) && ! array_is_list($existing['tools'])
                ? $existing['tools']
                : [],
        ];
    }

    /**
     * @param JsonObject $document
     */
    public function write(string $path, array $document): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create config directory "%s".', $directory));
        }

        $encoded = json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $temporaryPath = $path . '.tmp.' . bin2hex(random_bytes(8));

        if (file_put_contents($temporaryPath, $encoded) === false) {
            throw new RuntimeException(sprintf('Could not write temporary config "%s".', $temporaryPath));
        }

        if (PHP_OS_FAMILY === 'Windows' && is_file($path) && ! unlink($path)) {
            throw new RuntimeException(sprintf('Could not replace config "%s".', $path));
        }

        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new RuntimeException(sprintf('Could not move config into place "%s".', $path));
        }
    }

    /**
     * @param JsonObject $defaults
     *
     * @return JsonObject
     */
    private function mergeKnownObject(array $defaults, mixed $existing): array
    {
        if (! is_array($existing) || array_is_list($existing)) {
            return $defaults;
        }

        $merged = $defaults;

        foreach (array_keys($defaults) as $key) {
            if (array_key_exists($key, $existing)) {
                $merged[$key] = $existing[$key];
            }
        }

        return $merged;
    }
}
