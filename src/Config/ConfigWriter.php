<?php

declare(strict_types=1);

namespace Sift\Config;

use Sift\Filesystem\JsonFile;

/**
 * @phpstan-type JsonObject array<string, mixed>
 */
final readonly class ConfigWriter
{
    public function __construct(
        private JsonFile $jsonFile = new JsonFile(),
    ) {}

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
        $this->jsonFile->writeObject($path, $document);
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
