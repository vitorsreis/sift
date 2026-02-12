<?php

declare(strict_types=1);

namespace Sift\Runtime;

use JsonException;
use Sift\Exceptions\UserFacingException;

final class ConfigLoader
{
    /**
     * @return array{
     *   history: array{enabled: bool},
     *   output: array{format: string, size: string, pretty: bool},
     *   tools: array<string, array{enabled: bool, toolBinary: ?string, defaultArgs: list<string>, blockedArgs: list<string>}>
     * }
     */
    public function load(string $cwd, ?string $configPath = null): array
    {
        $path = $this->path($cwd, $configPath);

        if (! is_file($path)) {
            return $this->defaults();
        }

        $contents = file_get_contents($path);

        if (! is_string($contents) || trim($contents) === '') {
            throw UserFacingException::invalidConfig($path, 'The config file is empty.');
        }

        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw UserFacingException::invalidConfig($path, $exception->getMessage());
        }

        if (! is_array($decoded)) {
            throw UserFacingException::invalidConfig($path, 'The config root must be a JSON object.');
        }

        $tools = $decoded['tools'] ?? null;

        if (! is_array($tools)) {
            throw UserFacingException::invalidConfig($path, 'The `tools` key must be a JSON object.');
        }

        $defaults = $this->defaults();
        $history = is_array($decoded['history'] ?? null) ? $decoded['history'] : [];
        $output = is_array($decoded['output'] ?? null) ? $decoded['output'] : [];

        $format = $output['format'] ?? $defaults['output']['format'];
        $size = $output['size'] ?? $defaults['output']['size'];
        $pretty = $output['pretty'] ?? $defaults['output']['pretty'];

        if (! in_array($format, ['json', 'markdown'], true)) {
            throw UserFacingException::invalidConfig($path, 'The `output.format` value must be `json` or `markdown`.');
        }

        if (! in_array($size, ['compact', 'normal', 'fuller'], true)) {
            throw UserFacingException::invalidConfig($path, 'The `output.size` value must be `compact`, `normal`, or `fuller`.');
        }

        if (! is_bool($pretty)) {
            throw UserFacingException::invalidConfig($path, 'The `output.pretty` value must be boolean.');
        }

        $enabled = $history['enabled'] ?? $defaults['history']['enabled'];

        if (! is_bool($enabled)) {
            throw UserFacingException::invalidConfig($path, 'The `history.enabled` value must be boolean.');
        }

        $normalizedTools = [];

        foreach ($tools as $tool => $toolConfig) {
            if (! is_string($tool) || $tool === '') {
                throw UserFacingException::invalidConfig($path, 'Tool names must be non-empty strings.');
            }

            if (! is_array($toolConfig)) {
                throw UserFacingException::invalidConfig($path, sprintf('The tool `%s` config must be a JSON object.', $tool));
            }

            $toolEnabled = $toolConfig['enabled'] ?? true;
            $toolBinary = $toolConfig['toolBinary'] ?? null;
            $defaultArgs = $toolConfig['defaultArgs'] ?? [];
            $blockedArgs = $toolConfig['blockedArgs'] ?? [];

            if (! is_bool($toolEnabled)) {
                throw UserFacingException::invalidConfig($path, sprintf('The tool `%s.enabled` value must be boolean.', $tool));
            }

            if ($toolBinary !== null && (! is_string($toolBinary) || trim($toolBinary) === '')) {
                throw UserFacingException::invalidConfig($path, sprintf('The tool `%s.toolBinary` value must be a non-empty string.', $tool));
            }

            if (! is_array($defaultArgs)) {
                throw UserFacingException::invalidConfig($path, sprintf('The tool `%s.defaultArgs` value must be an array.', $tool));
            }

            if (! is_array($blockedArgs)) {
                throw UserFacingException::invalidConfig($path, sprintf('The tool `%s.blockedArgs` value must be an array.', $tool));
            }

            $normalizedTools[$tool] = [
                'enabled' => $toolEnabled,
                'toolBinary' => $toolBinary !== null ? trim($toolBinary) : null,
                'defaultArgs' => array_values(array_map('strval', $defaultArgs)),
                'blockedArgs' => array_values(array_map('strval', $blockedArgs)),
            ];
        }

        return [
            'history' => [
                'enabled' => $enabled,
            ],
            'output' => [
                'format' => $format,
                'size' => $size,
                'pretty' => $pretty,
            ],
            'tools' => $normalizedTools,
        ];
    }

    public function path(string $cwd, ?string $configPath = null): string
    {
        if ($configPath === null) {
            return $cwd.DIRECTORY_SEPARATOR.'sift.json';
        }

        if ($this->isAbsolutePath($configPath)) {
            return $configPath;
        }

        return $cwd.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configPath);
    }

    /**
     * @param  array{
     *   history: array{enabled: bool},
     *   output: array{format: string, size: string, pretty: bool},
     *   tools: array<string, array{enabled: bool, toolBinary: ?string, defaultArgs: list<string>, blockedArgs: list<string>}>
     * }  $config
     * @return array{enabled: bool, toolBinary: ?string, defaultArgs: list<string>, blockedArgs: list<string>}
     */
    public function tool(array $config, string $tool): array
    {
        $toolConfig = $config['tools'][$tool] ?? null;

        if (! is_array($toolConfig)) {
            return [
                'enabled' => true,
                'toolBinary' => null,
                'defaultArgs' => [],
                'blockedArgs' => [],
            ];
        }

        return [
            'enabled' => (bool) ($toolConfig['enabled'] ?? true),
            'toolBinary' => is_string($toolConfig['toolBinary'] ?? null) && trim($toolConfig['toolBinary']) !== ''
                ? trim((string) $toolConfig['toolBinary'])
                : null,
            'defaultArgs' => is_array($toolConfig['defaultArgs'] ?? null)
                ? array_values(array_map('strval', $toolConfig['defaultArgs']))
                : [],
            'blockedArgs' => is_array($toolConfig['blockedArgs'] ?? null)
                ? array_values(array_map('strval', $toolConfig['blockedArgs']))
                : [],
        ];
    }

    /**
     * @return array{
     *   history: array{enabled: bool},
     *   output: array{format: string, size: string, pretty: bool},
     *   tools: array<string, array<string, mixed>>
     * }
     */
    private function defaults(): array
    {
        return [
            'history' => [
                'enabled' => true,
            ],
            'output' => [
                'format' => 'json',
                'size' => 'normal',
                'pretty' => false,
            ],
            'tools' => [],
        ];
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
