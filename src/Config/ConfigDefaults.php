<?php

declare(strict_types=1);

namespace Sift\Config;

use Sift\Sift;

final class ConfigDefaults
{
    public static function schemaUrl(): string
    {
        return 'https://raw.githubusercontent.com/vitorsreis/sift/v' . Sift::VERSION . '/resources/schema.json';
    }

    /**
     * @return array{enabled: bool, path: string, max_files: int, max_age_days: int, max_bytes_per_run: int, redact_secrets: bool}
     */
    public static function history(): array
    {
        return [
            'enabled' => true,
            'path' => '.sift/history',
            'max_files' => 50,
            'max_age_days' => 30,
            'max_bytes_per_run' => 1048576,
            'redact_secrets' => true,
        ];
    }

    /**
     * @return array{size: string, pretty: bool, show_process: bool}
     */
    public static function output(): array
    {
        return [
            'size' => 'compact',
            'pretty' => true,
            'show_process' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(): array
    {
        return [
            '$schema' => self::schemaUrl(),
            'output' => self::output(),
            'history' => self::history(),
            'tools' => [],
        ];
    }
}
