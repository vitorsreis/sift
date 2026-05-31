<?php

declare(strict_types=1);

it('declares the package contract required for the rebuild', function (): void {
    $manifestPath = dirname(__DIR__, 3) . '/composer.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['name'] ?? null)->toBe('vitorsreis/sift');
    expect($manifest['type'] ?? null)->toBe('composer-plugin');
    expect($manifest['license'] ?? null)->toBe('MIT');
    expect($manifest['require']['php'] ?? null)->toBe('^8.3');
    expect($manifest['require']['composer-plugin-api'] ?? null)->toBe('^2.6');
    expect($manifest['require']['ext-json'] ?? null)->toBe('*');
    expect($manifest['require']['ext-simplexml'] ?? null)->toBe('*');
    expect($manifest['require']['symfony/console'] ?? null)->toBeNull();
    expect($manifest['require']['symfony/process'] ?? null)->toBeNull();
    expect($manifest['autoload']['psr-4']['Sift\\'] ?? null)->toBe('src/');
    expect($manifest['autoload-dev']['psr-4']['Tests\\'] ?? null)->toBe('tests/');
    expect($manifest['bin'] ?? null)->toBe(['bin/sift']);
    expect($manifest['extra']['class'] ?? null)->toBe('Sift\\Composer\\SiftPlugin');
    expect(array_keys($manifest['scripts'] ?? []))->toContain('test', 'analyse', 'format', 'rector', 'quality');
});
