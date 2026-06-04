<?php

declare(strict_types=1);

namespace Sift\History;

use DateTimeImmutable;

final readonly class RunIdFormat
{
    public const string CORE_PATTERN = '/^[0-9a-z]{14}$/';

    public const string FILE_PATTERN = '/^sift_([0-9a-z]{14})_[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public static function isValid(string $value): bool
    {
        return self::isCore($value);
    }

    public static function isCore(string $value): bool
    {
        return preg_match(self::CORE_PATTERN, $value) === 1;
    }

    public static function core(string $value): ?string
    {
        if (self::isCore($value)) {
            return $value;
        }

        return null;
    }

    public static function fileCore(string $value): ?string
    {
        if (preg_match(self::FILE_PATTERN, $value, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public static function fileId(string $core, string $tool): ?string
    {
        if (! self::isCore($core)) {
            return null;
        }

        return 'sift_' . $core . '_' . self::toolSlug($tool);
    }

    public static function createdAt(string $value): ?DateTimeImmutable
    {
        $core = self::core($value);

        if ($core === null) {
            return null;
        }

        $timestamp = base_convert(substr($core, 0, 7), 36, 10);

        if ($timestamp === '') {
            return null;
        }

        return new DateTimeImmutable('@' . $timestamp);
    }

    public static function toolSlug(string $tool): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/', '-', strtolower($tool));
        $slug = is_string($normalized) ? trim($normalized, '-') : '';

        return $slug === '' ? 'unknown' : $slug;
    }
}
