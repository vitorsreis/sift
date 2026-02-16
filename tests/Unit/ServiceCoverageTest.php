<?php

declare(strict_types=1);

use Sift\Exceptions\UserFacingException;
use Sift\Registry\ToolRegistry;
use Sift\Runtime\AddService;
use Sift\Runtime\ConfigDocumentManager;
use Sift\Runtime\ConfigLoader;
use Sift\Runtime\InitService;
use Sift\Runtime\ProjectInspector;
use Sift\Runtime\ToolLocator;
use Sift\Runtime\ValidateService;
use Tests\Support\FakeToolAdapter;

it('adds an installed tool to the config with a portable binary', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, 'demo', "<?php\n");
        $service = new AddService(
            new ToolLocator(PHP_BINARY),
            new ConfigDocumentManager(new ConfigLoader),
        );
        $registry = new ToolRegistry([
            new FakeToolAdapter('demo', 'Install demo.', ['vendor/bin/demo'], [
                'enabled' => false,
                'defaultArgs' => ['--memory-limit=1G'],
            ]),
        ]);

        $result = $service->add($cwd, 'demo', $registry);
        $config = json_decode((string) file_get_contents($cwd.DIRECTORY_SEPARATOR.'sift.json'), true, flags: JSON_THROW_ON_ERROR);

        expect($result['status'])->toBe('added')
            ->and($result['tool'])->toBe('demo')
            ->and($result['config_created'])->toBeTrue()
            ->and($config['tools']['demo']['toolBinary'])->toBe('vendor/bin/demo')
            ->and($config['tools']['demo']['enabled'])->toBeTrue()
            ->and($config['tools']['demo']['defaultArgs'])->toBe(['--memory-limit=1G']);
    } finally {
        removeDirectory($cwd);
    }
});

it('initializes config documents with detected installed tools only', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, 'beta', "<?php\n");
        $service = new InitService(
            new ToolLocator(PHP_BINARY),
            new ConfigDocumentManager(new ConfigLoader),
        );
        $registry = new ToolRegistry([
            new FakeToolAdapter('zeta', 'Install zeta.', ['vendor/bin/zeta']),
            new FakeToolAdapter('beta', 'Install beta.', ['vendor/bin/beta']),
        ]);

        $result = $service->initialize($cwd, false, $registry);
        $config = json_decode((string) file_get_contents($cwd.DIRECTORY_SEPARATOR.'sift.json'), true, flags: JSON_THROW_ON_ERROR);

        expect($result['status'])->toBe('initialized')
            ->and($result['tools'])->toBe(['beta'])
            ->and($result['detected'][0]['tool'] ?? null)->toBe('beta')
            ->and(array_keys($config['tools']))->toBe(['beta'])
            ->and($config['tools']['beta']['toolBinary'])->toBe('vendor/bin/beta');
    } finally {
        removeDirectory($cwd);
    }
});

it('rejects init when the config already exists and force is disabled', function (): void {
    $cwd = makeTempDirectory();

    try {
        writeSiftConfig($cwd, ['tools' => (object) []]);
        $service = new InitService(
            new ToolLocator(PHP_BINARY),
            new ConfigDocumentManager(new ConfigLoader),
        );

        expect(fn () => $service->initialize($cwd, false, new ToolRegistry))
            ->toThrow(UserFacingException::class);
    } finally {
        removeDirectory($cwd);
    }
});

it('inspects project tools using configured binary overrides', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, 'custom-demo', "<?php\n");
        $inspector = new ProjectInspector(new ToolLocator(PHP_BINARY));
        $registry = new ToolRegistry([
            new FakeToolAdapter('demo', 'Install demo.', ['vendor/bin/demo']),
            new FakeToolAdapter('missing', 'Install missing.', ['vendor/bin/missing']),
        ]);

        $items = $inspector->inspect($cwd, $registry, [
            'tools' => [
                'demo' => [
                    'enabled' => false,
                    'toolBinary' => 'vendor/bin/custom-demo',
                ],
            ],
        ]);

        expect($items)->toHaveCount(2)
            ->and($items[0]['tool'])->toBe('demo')
            ->and($items[0]['enabled'])->toBeFalse()
            ->and($items[0]['installed'])->toBeTrue()
            ->and($items[0]['configured_binary'])->toBe('vendor/bin/custom-demo')
            ->and(str_replace('\\', '/', (string) $items[0]['path']))->toContain('vendor/bin/custom-demo')
            ->and($items[1]['installed'])->toBeFalse();
    } finally {
        removeDirectory($cwd);
    }
});

it('validates an existing config and reports normalized settings', function (): void {
    $cwd = makeTempDirectory();

    try {
        writeSiftConfig($cwd, [
            'history' => [
                'enabled' => true,
                'max_files' => 10,
                'path' => '.sift/custom-history',
            ],
            'output' => [
                'format' => 'markdown',
                'size' => 'compact',
                'pretty' => true,
                'show_process' => true,
            ],
            'tools' => [
                'demo' => [
                    'enabled' => false,
                    'toolBinary' => 'vendor/bin/demo',
                    'defaultArgs' => ['--ansi'],
                    'blockedArgs' => ['--danger'],
                ],
            ],
        ]);
        $service = new ValidateService(new ConfigLoader);

        $result = $service->validate($cwd);

        expect($result['status'])->toBe('valid')
            ->and($result['tools'])->toBe(['demo'])
            ->and($result['tool_settings']['demo']['toolBinary'])->toBe('vendor/bin/demo')
            ->and($result['output'])->toBe([
                'format' => 'markdown',
                'size' => 'compact',
                'pretty' => true,
                'show_process' => true,
            ])
            ->and($result['history']['max_files'])->toBe(10);
    } finally {
        removeDirectory($cwd);
    }
});
