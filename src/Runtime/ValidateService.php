<?php

declare(strict_types=1);

namespace Sift\Runtime;

use JsonException;
use Sift\Exceptions\UserFacingException;

final class ValidateService
{
    /**
     * @return array<string, mixed>
     */
    public function validate(string $cwd): array
    {
        $path = $cwd.DIRECTORY_SEPARATOR.'sift.json';

        if (! is_file($path)) {
            throw UserFacingException::configNotFound($path);
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

        $output = is_array($decoded['output'] ?? null) ? $decoded['output'] : [];
        $format = $output['format'] ?? 'json';
        $size = $output['size'] ?? 'normal';

        if (! in_array($format, ['json', 'markdown'], true)) {
            throw UserFacingException::invalidConfig($path, 'The `output.format` value must be `json` or `markdown`.');
        }

        if (! in_array($size, ['compact', 'normal', 'fuller'], true)) {
            throw UserFacingException::invalidConfig($path, 'The `output.size` value must be `compact`, `normal`, or `fuller`.');
        }

        return [
            'status' => 'valid',
            'path' => $path,
            'tools' => array_keys($tools),
            'output' => [
                'format' => $format,
                'size' => $size,
            ],
        ];
    }
}
