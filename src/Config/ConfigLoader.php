<?php

declare(strict_types=1);

namespace Sift\Config;

use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\JsonFile;
use Sift\Filesystem\Path;
use Sift\Workspace\Workspace;

/**
 * @phpstan-type JsonObject array<string, mixed>
 */
final readonly class ConfigLoader
{
    public function __construct(
        private JsonFile $jsonFile = new JsonFile(),
    ) {}

    public function load(Workspace $workspace): SiftConfig
    {
        $configPath = $workspace->configPath();
        $baseDirectory = $configPath !== null ? Path::normalize(dirname($configPath)) : $workspace->projectRoot();

        if ($configPath === null || ! is_file($configPath)) {
            return $this->fromDocument(
                document: ConfigDefaults::document(),
                configPath: $configPath,
                baseDirectory: $workspace->projectRoot(),
                usingDefaults: true,
            );
        }

        $document = $this->readDocument($configPath);

        return $this->fromDocument(
            document: $document,
            configPath: $configPath,
            baseDirectory: $baseDirectory,
            usingDefaults: false,
        );
    }

    /**
     * @return JsonObject
     */
    public function readDocument(string $path): array
    {
        try {
            return $this->jsonFile->readObject($path);
        } catch (FilesystemException $filesystemException) {
            throw ConfigValidationException::invalidConfig($path, $filesystemException->getMessage());
        }
    }

    /**
     * @param JsonObject $document
     */
    private function fromDocument(array $document, ?string $configPath, string $baseDirectory, bool $usingDefaults): SiftConfig
    {
        $path = $configPath;

        $this->rejectUnknownKeys($document, ['$schema', 'history', 'output', 'tools'], $path, 'config');

        if (array_key_exists('version', $document)) {
            throw ConfigValidationException::invalidConfig($path, 'The top-level `version` field is not supported.');
        }

        $history = $this->historyConfig($this->optionalObject($document, 'history', $path), $baseDirectory, $path);
        $output = $this->outputConfig($this->optionalObject($document, 'output', $path), $path);
        $tools = $this->toolConfigs($this->optionalObject($document, 'tools', $path), $baseDirectory, $path);

        return new SiftConfig(
            schema: ConfigDefaults::schemaUrl(),
            configPath: $configPath,
            usingDefaults: $usingDefaults,
            history: $history,
            output: $output,
            tools: $tools,
        );
    }

    /**
     * @param JsonObject $document
     *
     * @return JsonObject
     */
    private function optionalObject(array $document, string $key, ?string $path): array
    {
        $value = $document[$key] ?? [];

        if ($value === []) {
            return [];
        }

        if (! is_array($value) || array_is_list($value)) {
            throw ConfigValidationException::invalidConfig($path, sprintf('The `%s` field must be a JSON object.', $key));
        }

        return $this->stringKeyedObject($value, $path, $key);
    }

    /**
     * @param JsonObject $history
     */
    private function historyConfig(array $history, string $baseDirectory, ?string $path): HistoryConfig
    {
        $defaults = ConfigDefaults::history();
        $this->rejectUnknownKeys($history, array_keys($defaults), $path, 'history');

        $enabled = $this->boolValue($history, 'enabled', $defaults['enabled'], $path, 'history.enabled');
        $historyPath = $this->stringValue($history, 'path', $defaults['path'], $path, 'history.path');
        $maxFiles = $this->intValue($history, 'max_files', $defaults['max_files'], $path, 'history.max_files', minimum: 1);
        $maxAgeDays = $this->optionalIntValue($history, 'max_age_days', $path, 'history.max_age_days', minimum: 1);
        $maxBytesPerRun = $this->intValue($history, 'max_bytes_per_run', $defaults['max_bytes_per_run'], $path, 'history.max_bytes_per_run', minimum: 1024);
        $redactSecrets = $this->boolValue($history, 'redact_secrets', $defaults['redact_secrets'], $path, 'history.redact_secrets');

        return new HistoryConfig(
            enabled: $enabled,
            path: $this->resolveRelativePath($baseDirectory, $historyPath),
            maxFiles: $maxFiles,
            maxAgeDays: $maxAgeDays,
            maxBytesPerRun: $maxBytesPerRun,
            redactSecrets: $redactSecrets,
            defaultPath: Path::normalize($historyPath) === Path::normalize($defaults['path']),
        );
    }

    /**
     * @param JsonObject $output
     */
    private function outputConfig(array $output, ?string $path): OutputConfig
    {
        $defaults = ConfigDefaults::output();
        $this->rejectUnknownKeys($output, array_keys($defaults), $path, 'output');

        $size = $this->stringValue($output, 'size', $defaults['size'], $path, 'output.size');

        if (! in_array($size, ['compact', 'normal', 'full'], true)) {
            throw ConfigValidationException::invalidConfig($path, 'The `output.size` value must be `compact`, `normal`, or `full`.');
        }

        return new OutputConfig(
            size: $size,
            pretty: $this->boolValue($output, 'pretty', $defaults['pretty'], $path, 'output.pretty'),
            showProcess: $this->boolValue($output, 'show_process', $defaults['show_process'], $path, 'output.show_process'),
        );
    }

    /**
     * @param JsonObject $tools
     *
     * @return array<string, ToolConfig>
     */
    private function toolConfigs(array $tools, string $baseDirectory, ?string $path): array
    {
        $configs = [];
        $wildcardInput = $this->toolObject($tools['*'] ?? [], '*', $path);
        $this->rejectUnknownKeys($wildcardInput, ['enabled', 'timeout'], $path, 'tools.*');

        $wildcard = new ToolConfig(
            name: '*',
            enabled: $this->boolValue($wildcardInput, 'enabled', true, $path, 'tools.*.enabled'),
            binary: null,
            blockedArgs: [],
            timeout: $this->intValue($wildcardInput, 'timeout', 1800, $path, 'tools.*.timeout', minimum: 0),
        );
        $configs['*'] = $wildcard;

        foreach ($tools as $name => $tool) {
            if ($name === '*') {
                continue;
            }

            if ($name === '') {
                throw ConfigValidationException::invalidConfig($path, 'Tool names must be non-empty strings.');
            }

            $toolInput = $this->toolObject($tool, $name, $path);
            $this->rejectUnknownKeys($toolInput, ['enabled', 'binary', 'blocked_args', 'timeout'], $path, 'tools.' . $name);
            $binary = $this->optionalStringValue($toolInput, 'binary', $path, 'tools.' . $name . '.binary');

            $configs[$name] = new ToolConfig(
                name: $name,
                enabled: $this->boolValue($toolInput, 'enabled', $wildcard->enabled(), $path, 'tools.' . $name . '.enabled'),
                binary: $binary === null ? null : $this->resolveBinary($baseDirectory, $binary),
                blockedArgs: $this->stringListValue($toolInput, 'blocked_args', $path, 'tools.' . $name . '.blocked_args'),
                timeout: $this->intValue($toolInput, 'timeout', $wildcard->timeout(), $path, 'tools.' . $name . '.timeout', minimum: 0),
            );
        }

        return $configs;
    }

    /**
     * @return JsonObject
     */
    private function toolObject(mixed $tool, string $name, ?string $path): array
    {
        if ($tool === []) {
            return [];
        }

        if (! is_array($tool) || array_is_list($tool)) {
            throw ConfigValidationException::invalidConfig($path, sprintf('The `tools.%s` field must be a JSON object.', $name));
        }

        return $this->stringKeyedObject($tool, $path, 'tools.' . $name);
    }

    /**
     * @param JsonObject $object
     * @param list<string> $allowed
     */
    private function rejectUnknownKeys(array $object, array $allowed, ?string $path, string $context): void
    {
        foreach (array_keys($object) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw ConfigValidationException::invalidConfig($path, sprintf('Unknown `%s.%s` config field.', $context, $key));
            }
        }
    }

    /**
     * @param JsonObject $object
     */
    private function boolValue(array $object, string $key, bool $default, ?string $path, string $label): bool
    {
        $value = $object[$key] ?? $default;

        if (! is_bool($value)) {
            throw ConfigValidationException::invalidConfig($path, sprintf('The `%s` value must be boolean.', $label));
        }

        return $value;
    }

    /**
     * @param JsonObject $object
     */
    private function stringValue(array $object, string $key, string $default, ?string $path, string $label): string
    {
        $value = $object[$key] ?? $default;

        if (! is_string($value) || trim($value) === '') {
            throw ConfigValidationException::invalidConfig($path, sprintf('The `%s` value must be a non-empty string.', $label));
        }

        return trim($value);
    }

    /**
     * @param JsonObject $object
     */
    private function optionalStringValue(array $object, string $key, ?string $path, string $label): ?string
    {
        if (! array_key_exists($key, $object)) {
            return null;
        }

        $value = $object[$key];

        if (! is_string($value) || trim($value) === '') {
            throw ConfigValidationException::invalidConfig($path, sprintf('The `%s` value must be a non-empty string.', $label));
        }

        return trim($value);
    }

    /**
     * @param JsonObject $object
     */
    private function intValue(array $object, string $key, int $default, ?string $path, string $label, int $minimum): int
    {
        $value = $object[$key] ?? $default;

        if (! is_int($value) || $value < $minimum) {
            throw ConfigValidationException::invalidConfig($path, sprintf('The `%s` value must be an integer greater than or equal to %d.', $label, $minimum));
        }

        return $value;
    }

    /**
     * @param JsonObject $object
     */
    private function optionalIntValue(array $object, string $key, ?string $path, string $label, int $minimum): ?int
    {
        if (! array_key_exists($key, $object)) {
            return null;
        }

        $value = $object[$key];

        if (! is_int($value) || $value < $minimum) {
            throw ConfigValidationException::invalidConfig($path, sprintf('The `%s` value must be an integer greater than or equal to %d.', $label, $minimum));
        }

        return $value;
    }

    /**
     * @param JsonObject $object
     *
     * @return list<string>
     */
    private function stringListValue(array $object, string $key, ?string $path, string $label): array
    {
        $value = $object[$key] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            throw ConfigValidationException::invalidConfig($path, sprintf('The `%s` value must be an array of strings.', $label));
        }

        $strings = [];

        foreach ($value as $entry) {
            if (! is_string($entry)) {
                throw ConfigValidationException::invalidConfig($path, sprintf('The `%s` entries must be strings.', $label));
            }

            $strings[] = $entry;
        }

        return $strings;
    }

    /**
     * @param array<mixed> $object
     *
     * @return JsonObject
     */
    private function stringKeyedObject(array $object, ?string $path, string $label): array
    {
        $normalized = [];

        foreach ($object as $key => $value) {
            if (! is_string($key)) {
                throw ConfigValidationException::invalidConfig($path, sprintf('The `%s` object must use string keys.', $label));
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function resolveRelativePath(string $baseDirectory, string $path): string
    {
        if (Path::isAbsolute($path)) {
            return Path::normalize($path);
        }

        return Path::join($baseDirectory, $path);
    }

    private function resolveBinary(string $baseDirectory, string $binary): string
    {
        if (Path::isAbsolute($binary)) {
            return Path::normalize($binary);
        }

        if (! Path::containsDirectorySeparator($binary)) {
            return $binary;
        }

        return Path::join($baseDirectory, $binary);
    }
}
