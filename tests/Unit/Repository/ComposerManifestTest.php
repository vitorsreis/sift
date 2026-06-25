<?php

declare(strict_types=1);

use Sift\Composer\SiftPlugin;

/**
 * @return array{
 *     name: string,
 *     type: string,
 *     license: string,
 *     require: array<string, string>,
 *     autoload: array{'psr-4': array<string, string>},
 *     autoload-dev: array{'psr-4': array<string, string>},
 *     bin: list<string>,
 *     extra: array{class: string},
 *     scripts: array<string, mixed>
 * }
 */
function composerManifest(): array
{
    $manifestPath = dirname(__DIR__, 3) . '/composer.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($manifest)) {
        throw new RuntimeException('Composer manifest must be an object.');
    }

    return [
        'name' => manifestString($manifest, 'name'),
        'type' => manifestString($manifest, 'type'),
        'license' => manifestString($manifest, 'license'),
        'require' => manifestStringMap($manifest, 'require'),
        'autoload' => ['psr-4' => manifestStringMap(manifestArray($manifest, 'autoload'), 'psr-4')],
        'autoload-dev' => ['psr-4' => manifestStringMap(manifestArray($manifest, 'autoload-dev'), 'psr-4')],
        'bin' => manifestStringList($manifest, 'bin'),
        'extra' => ['class' => manifestString(manifestArray($manifest, 'extra'), 'class')],
        'scripts' => manifestStringKeyedArray($manifest, 'scripts'),
    ];
}

/**
 * @param array<mixed> $payload
 */
function manifestString(array $payload, string $key): string
{
    $value = $payload[$key] ?? null;

    if (! is_string($value)) {
        throw new RuntimeException(sprintf('Expected string field "%s".', $key));
    }

    return $value;
}

/**
 * @param array<mixed> $payload
 *
 * @return array<mixed>
 */
function manifestArray(array $payload, string $key): array
{
    $value = $payload[$key] ?? null;

    if (! is_array($value)) {
        throw new RuntimeException(sprintf('Expected array field "%s".', $key));
    }

    return $value;
}

/**
 * @param array<mixed> $payload
 *
 * @return array<string, string>
 */
function manifestStringMap(array $payload, string $key): array
{
    $value = manifestArray($payload, $key);
    $map = [];

    foreach ($value as $mapKey => $mapValue) {
        if (! is_string($mapKey) || ! is_string($mapValue)) {
            throw new RuntimeException(sprintf('Expected string map field "%s".', $key));
        }

        $map[$mapKey] = $mapValue;
    }

    return $map;
}

/**
 * @param array<mixed> $payload
 *
 * @return array<string, mixed>
 */
function manifestStringKeyedArray(array $payload, string $key): array
{
    $value = manifestArray($payload, $key);
    $map = [];

    foreach ($value as $mapKey => $mapValue) {
        if (! is_string($mapKey)) {
            throw new RuntimeException(sprintf('Expected string-keyed array field "%s".', $key));
        }

        $map[$mapKey] = $mapValue;
    }

    return $map;
}

/**
 * @param array<mixed> $payload
 *
 * @return list<string>
 */
function manifestStringList(array $payload, string $key): array
{
    $value = manifestArray($payload, $key);
    $list = [];

    if (! array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected string list field "%s".', $key));
    }

    foreach ($value as $item) {
        if (! is_string($item)) {
            throw new RuntimeException(sprintf('Expected string list field "%s".', $key));
        }

        $list[] = $item;
    }

    return $list;
}

it('declares the package contract required for the rebuild', function (): void {
    $manifest = composerManifest();

    expect($manifest['name'])->toBe('vitorsreis/sift');
    expect($manifest['type'])->toBe('composer-plugin');
    expect($manifest['license'])->toBe('MIT');
    expect($manifest['require']['php'] ?? null)->toBe('^8.3');
    expect($manifest['require']['composer-plugin-api'] ?? null)->toBe('^2.6');
    expect($manifest['require']['ext-json'] ?? null)->toBe('*');
    expect($manifest['require']['ext-simplexml'] ?? null)->toBe('*');
    expect($manifest['require']['symfony/console'] ?? null)->toBeNull();
    expect($manifest['require']['symfony/process'] ?? null)->toBeNull();
    expect($manifest['autoload']['psr-4']['Sift\\'] ?? null)->toBe('src/');
    expect($manifest['autoload-dev']['psr-4']['Tests\\'] ?? null)->toBe('tests/');
    expect($manifest['bin'])->toBe(['bin/sift']);
    expect($manifest['extra']['class'])->toBe(SiftPlugin::class);
    expect(array_keys($manifest['scripts']))->toContain('sift', 'skills', 'test', 'analyse', 'format', 'rector', 'quality');
    expect($manifest['scripts']['skills'] ?? null)->toBe('@php bin/sift -- skills @additional_args');
});
