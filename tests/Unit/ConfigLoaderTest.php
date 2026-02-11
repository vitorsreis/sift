<?php

declare(strict_types=1);

use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ConfigLoader;

it('returns defaults when the config file is missing', function (): void {
    $cwd = makeTempDirectory();

    try {
        $config = (new ConfigLoader)->load($cwd);

        expect($config)->toBe([
            'history' => ['enabled' => true],
            'output' => ['format' => 'json', 'size' => 'normal', 'pretty' => false],
            'tools' => [],
        ]);
    } finally {
        removeDirectory($cwd);
    }
});

it('loads and normalizes a custom relative config path', function (): void {
    $cwd = makeTempDirectory();

    try {
        writeSiftConfig($cwd, [
            'history' => ['enabled' => false],
            'output' => ['format' => 'markdown', 'size' => 'fuller', 'pretty' => true],
            'tools' => [
                'pint' => ['enabled' => false, 'defaultArgs' => ['--test', 123]],
            ],
        ], 'custom.sift.json');

        $config = (new ConfigLoader)->load($cwd, 'custom.sift.json');

        expect($config)->toBe([
            'history' => ['enabled' => false],
            'output' => ['format' => 'markdown', 'size' => 'fuller', 'pretty' => true],
            'tools' => [
                'pint' => ['enabled' => false, 'defaultArgs' => ['--test', '123']],
            ],
        ]);
    } finally {
        removeDirectory($cwd);
    }
});

it('resolves absolute config paths', function (): void {
    $cwd = makeTempDirectory();
    $other = makeTempDirectory();

    try {
        $path = writeSiftConfig($other, [
            'history' => ['enabled' => true],
            'output' => ['format' => 'json', 'size' => 'compact', 'pretty' => true],
            'tools' => [],
        ]);

        $loader = new ConfigLoader;

        expect($loader->path($cwd, $path))->toBe($path)
            ->and($loader->load($cwd, $path)['output']['size'])->toBe('compact');
    } finally {
        removeDirectory($cwd);
        removeDirectory($other);
    }
});

it('rejects invalid config documents', function (): void {
    $cwd = makeTempDirectory();
    $path = $cwd.DIRECTORY_SEPARATOR.'sift.json';

    try {
        file_put_contents($path, '{ invalid');

        try {
            (new ConfigLoader)->load($cwd);
            $this->fail('Expected invalid config exception.');
        } catch (UserFacingException $exception) {
            expect($exception->payload()['error']['code'])->toBe('invalid_config')
                ->and($exception->payload()['error']['path'])->toBe($path);
        }
    } finally {
        removeDirectory($cwd);
    }
});
