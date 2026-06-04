<?php

declare(strict_types=1);

namespace Sift\History;

final readonly class RunIdFormat
{
    public const string CORE_PATTERN = '/^[0-9a-z]{14}$/';

    public const string FILE_PATTERN = '/^sift_([0-9a-z]{14})_[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const string PATTERN = '/^(?:[0-9a-z]{14}|sift_[0-9a-z]{14}_[a-z0-9]+(?:-[a-z0-9]+)*)$/';

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
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

    public static function toolSlug(string $tool): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/', '-', strtolower($tool));
        $slug = is_string($normalized) ? trim($normalized, '-') : '';

        return $slug === '' ? 'unknown' : $slug;
    }
}
