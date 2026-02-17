<?php

declare(strict_types=1);

namespace Sift\Runtime;

final class ConfigDefaults
{
    /**
     * @return array{enabled: bool, max_files: int, path: string}
     */
    public static function history(): array
    {
        return [
            'enabled' => true,
            'max_files' => 50,
            'path' => '.sift/history',
        ];
    }

    /**
     * @return array{format: string, size: string, pretty?: bool, show_process: bool}
     */
    public static function output(bool $includePretty = true): array
    {
        $output = [
            'format' => 'json',
            'size' => 'normal',
        ];

        if ($includePretty) {
            $output['pretty'] = false;
        }

        $output['show_process'] = false;

        return $output;
    }

    /**
     * @return array{
     *   history: array{enabled: bool, max_files: int, path: string},
     *   output: array{format: string, size: string, pretty: bool, show_process: bool},
     *   tools: array<string, array<string, mixed>>
     * }
     */
    public static function runtime(): array
    {
        return [
            'history' => self::history(),
            'output' => self::output(),
            'tools' => [],
        ];
    }

    /**
     * @return array{
     *   $schema: string,
     *   history: array{enabled: bool, max_files: int, path: string},
     *   output: array{format: string, size: string, show_process: bool},
     *   tools: array<string, array<string, mixed>>
     * }
     */
    public static function document(): array
    {
        return [
            '$schema' => './resources/schema/config.schema.json',
            'history' => self::history(),
            'output' => self::output(includePretty: false),
            'tools' => [],
        ];
    }
}
