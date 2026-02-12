<?php

declare(strict_types=1);

namespace Sift\Runtime;

use JsonException;
use Sift\Exceptions\UserFacingException;

final class ConfigDocumentManager
{
    public function __construct(
        private readonly ConfigLoader $configLoader,
    ) {}

    public function path(string $cwd, ?string $configPath = null): string
    {
        return $this->configLoader->path($cwd, $configPath);
    }

    /**
     * @return array<string, mixed>
     */
    public function readOrDefault(string $cwd, ?string $configPath = null): array
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

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function write(string $cwd, array $document, ?string $configPath = null): void
    {
        $path = $this->path($cwd, $configPath);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw UserFacingException::invalidConfig($path, sprintf('Unable to create config directory `%s`.', $directory));
        }

        $encoded = json_encode(
            $this->prepareForWrite($document),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if (! is_string($encoded)) {
            throw UserFacingException::invalidConfig($path, 'Unable to encode the config document.');
        }

        file_put_contents($path, $this->withTwoSpaceIndentation($encoded).PHP_EOL);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            '$schema' => './resources/schema/config.schema.json',
            'history' => [
                'enabled' => true,
            ],
            'output' => [
                'format' => 'json',
                'size' => 'normal',
            ],
            'tools' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function normalize(array $document): array
    {
        $defaults = $this->defaults();
        $schema = is_string($document['$schema'] ?? null) && trim((string) $document['$schema']) !== ''
            ? trim((string) $document['$schema'])
            : $defaults['$schema'];
        $history = is_array($document['history'] ?? null) ? $document['history'] : $defaults['history'];
        $output = is_array($document['output'] ?? null) ? $document['output'] : $defaults['output'];
        $tools = $this->normalizeTools($document['tools'] ?? null);

        return [
            ...$document,
            '$schema' => $schema,
            'history' => $history,
            'output' => $output,
            'tools' => $tools,
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function prepareForWrite(array $document): array
    {
        $normalized = $this->normalize($document);
        $tools = is_array($normalized['tools']) ? $normalized['tools'] : [];
        $schema = (string) $normalized['$schema'];
        $history = is_array($normalized['history']) ? $normalized['history'] : [];
        $output = is_array($normalized['output']) ? $normalized['output'] : [];

        if ($tools !== []) {
            ksort($tools);
        }

        unset($normalized['$schema'], $normalized['history'], $normalized['output'], $normalized['tools']);

        return [
            '$schema' => $schema,
            'history' => $history,
            'output' => $output,
            'tools' => $tools === [] ? (object) [] : $tools,
            ...$normalized,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeTools(mixed $tools): array
    {
        if (is_array($tools)) {
            return $tools;
        }

        if (is_object($tools)) {
            /** @var array<string, mixed> $normalized */
            $normalized = get_object_vars($tools);

            return $normalized;
        }

        return [];
    }

    private function withTwoSpaceIndentation(string $encoded): string
    {
        return (string) preg_replace_callback(
            '/^( +)/m',
            static function (array $matches): string {
                $length = strlen($matches[1]);

                return str_repeat(' ', (int) floor($length / 2));
            },
            $encoded,
        );
    }
}
