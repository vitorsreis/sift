<?php

declare(strict_types=1);

use Sift\Console\Application;
use Sift\Console\ApplicationFactory;
use Sift\Console\OptionParser;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Registry\ToolRegistry;
use Sift\Renderers\JsonRenderer;
use Sift\Renderers\MarkdownRenderer;
use Sift\Runtime\AddService;
use Sift\Runtime\BlockedArgumentsPolicy;
use Sift\Runtime\ComposerCommandPolicy;
use Sift\Runtime\ConfigDocumentManager;
use Sift\Runtime\ConfigLoader;
use Sift\Runtime\FileRunStore;
use Sift\Runtime\InitService;
use Sift\Runtime\PolicyRunner;
use Sift\Runtime\ProcessExecutor;
use Sift\Runtime\ProjectInspector;
use Sift\Runtime\RectorCommandPolicy;
use Sift\Runtime\ResultMetaStamper;
use Sift\Runtime\ResultPayloadFactory;
use Sift\Runtime\ToolEnabledPolicy;
use Sift\Runtime\ToolInstalledPolicy;
use Sift\Runtime\ToolLocator;
use Sift\Runtime\ValidateService;
use Sift\Runtime\ViewService;
use Sift\Sift;
use Tests\Support\DummyExecutableToolAdapter;
use Tests\Support\FakeToolAdapter;

it('returns help and version payloads through the application handler', function (): void {
    $application = makeTestApplication(new ToolRegistry);

    $help = $application->handle([]);
    $version = $application->handle(['version']);

    expect($help['status'])->toBe('ok')
        ->and($help['tool'])->toBe('sift')
        ->and($help['commands'])->toContain('view')
        ->and($help['options'])->toContain('--raw | -r')
        ->and($version)->toBe([
            'status' => 'ok',
            'tool' => 'sift',
            'version' => Sift::VERSION,
            '_pretty' => false,
            '_format' => 'json',
        ]);
});

it('initializes lists and validates detected tools', function (): void {
    $cwd = makeTempDirectory('sift-application-init-');

    try {
        createProjectTool($cwd, 'dummy.php', "<?php\nexit(0);\n");
        $application = makeTestApplication(new ToolRegistry([
            new DummyExecutableToolAdapter(new ToolLocator(PHP_BINARY)),
            new FakeToolAdapter('missing', 'Install missing.', ['vendor/bin/missing.php']),
        ]));

        [$init, $list, $validate] = runApplicationInDirectory($cwd, static fn () => [
            $application->handle(['init']),
            $application->handle(['list']),
            $application->handle(['validate']),
        ]);

        expect($init['status'])->toBe('initialized')
            ->and($init['tools'])->toBe(['dummy'])
            ->and($list['status'])->toBe('ok')
            ->and($list['tool'])->toBe('sift')
            ->and($list['tools'])->toHaveCount(2)
            ->and($list['tools'][0]['tool'])->toBe('dummy')
            ->and($validate['status'])->toBe('valid')
            ->and($validate['tools'])->toBe(['dummy'])
            ->and($validate['tool_settings']['dummy']['toolBinary'])->toBe('vendor/bin/dummy.php');
    } finally {
        removeDirectory($cwd);
    }
});

it('adds a detected tool explicitly through the application handler', function (): void {
    $cwd = makeTempDirectory('sift-application-add-');

    try {
        createProjectTool($cwd, 'dummy.php', "<?php\nexit(0);\n");
        $application = makeTestApplication(new ToolRegistry([
            new DummyExecutableToolAdapter(new ToolLocator(PHP_BINARY)),
        ]));

        $payload = runApplicationInDirectory($cwd, static fn () => $application->handle(['add', 'dummy']));
        $config = json_decode((string) file_get_contents($cwd.DIRECTORY_SEPARATOR.'sift.json'), true, flags: JSON_THROW_ON_ERROR);

        expect($payload['status'])->toBe('added')
            ->and($payload['tool'])->toBe('dummy')
            ->and($config['tools']['dummy']['toolBinary'])->toBe('vendor/bin/dummy.php');
    } finally {
        removeDirectory($cwd);
    }
});

it('executes wrapped tools stores history and reads runs back through view', function (): void {
    $cwd = makeTempDirectory('sift-application-run-');

    try {
        createProjectTool($cwd, 'dummy.php', <<<'PHP'
<?php
fwrite(STDOUT, "wrapped-ok\n");
exit(0);
PHP);
        $application = makeTestApplication(new ToolRegistry([
            new DummyExecutableToolAdapter(new ToolLocator(PHP_BINARY)),
        ]));

        $payload = runApplicationInDirectory($cwd, static fn () => $application->handle(['dummy']));
        $runId = (string) ($payload['run_id'] ?? '');
        [$listing, $summary, $full] = runApplicationInDirectory($cwd, static fn () => [
            $application->handle(['view', 'list']),
            $application->handle(['view', $runId, 'summary']),
            $application->handle(['view', $runId]),
        ]);

        expect($payload['status'])->toBe('passed')
            ->and($payload['summary']['exit_code'])->toBe(0)
            ->and($runId)->not->toBe('')
            ->and($listing['status'])->toBe('ok')
            ->and($listing['count'])->toBeGreaterThanOrEqual(1)
            ->and($summary['status'])->toBe('passed')
            ->and($summary['summary'])->toBe(['exit_code' => 0])
            ->and($summary['run_id'])->toBe($runId)
            ->and($full['tool'])->toBe('dummy')
            ->and($full['run_id'])->toBe($runId);
    } finally {
        removeDirectory($cwd);
    }
});

it('returns passthrough payloads for raw executions', function (): void {
    $cwd = makeTempDirectory('sift-application-raw-');

    try {
        createProjectTool($cwd, 'dummy.php', <<<'PHP'
<?php
fwrite(STDOUT, "raw-stdout\n");
fwrite(STDERR, "raw-stderr\n");
exit(3);
PHP);
        $application = makeTestApplication(new ToolRegistry([
            new DummyExecutableToolAdapter(new ToolLocator(PHP_BINARY)),
        ]));

        $payload = runApplicationInDirectory($cwd, static fn () => $application->handle(['--raw', 'dummy', '--flag']));

        expect($payload['_passthrough'])->toBeTrue()
            ->and($payload['_process_exit_code'])->toBe(3)
            ->and($payload['_stdout'])->toBe("raw-stdout\n")
            ->and($payload['_stderr'])->toContain("raw-stderr\n");
    } finally {
        removeDirectory($cwd);
    }
});

it('throws a structured error for unsupported tools', function (): void {
    $application = makeTestApplication(new ToolRegistry);

    expect(fn () => $application->handle(['unknown-tool']))
        ->toThrow(UserFacingException::class);
});

it('covers application helper branches for render preferences and fallback config loading', function (): void {
    $cwd = makeTempDirectory('sift-application-helpers-');

    try {
        file_put_contents($cwd.DIRECTORY_SEPARATOR.'broken.sift.json', '{broken');
        $application = makeTestApplication(new ToolRegistry);

        [$defaults, $invalidPreferences, $addPreferences, $renderedMarkdown] = runApplicationInDirectory($cwd, static function () use ($application): array {
            return [
                invokeApplicationMethod($application, 'loadConfigOrDefaults', [getcwd() ?: '.', 'broken.sift.json']),
                invokeApplicationMethod($application, 'resolveRenderPreferences', [['--size=unknown']]),
                invokeApplicationMethod($application, 'resolveRenderPreferences', [['add', 'dummy', '-f', 'markdown', '-p']]),
                $application->render([
                    'status' => 'ok',
                    'summary' => ['tests' => 1],
                    '_format' => 'markdown',
                    '_pretty' => true,
                ]),
            ];
        });

        expect($defaults)->toBe([
            'history' => ['enabled' => true, 'max_files' => 50, 'path' => '.sift/history'],
            'output' => ['format' => 'json', 'size' => 'normal', 'pretty' => false, 'show_process' => false],
            'tools' => [],
        ])
            ->and($invalidPreferences)->toBe([
                'format' => 'json',
                'pretty' => false,
            ])
            ->and($addPreferences)->toBe([
                'format' => 'markdown',
                'pretty' => true,
            ])
            ->and($renderedMarkdown)->toContain('**status:** ok')
            ->and($renderedMarkdown)->toContain('ok');

        [$initPreferences, $validatePreferences, $viewPreferences, $jsonRender] = runApplicationInDirectory($cwd, static function () use ($application): array {
            return [
                invokeApplicationMethod($application, 'resolveRenderPreferences', [['init', '-f', 'markdown', '-p']]),
                invokeApplicationMethod($application, 'resolveRenderPreferences', [['validate', '-f', 'markdown', '-p']]),
                invokeApplicationMethod($application, 'resolveRenderPreferences', [['view', 'list', '-f', 'markdown', '-p']]),
                $application->render([
                    'status' => 'ok',
                    '_format' => 'json',
                    '_pretty' => false,
                ]),
            ];
        });

        expect($initPreferences)->toBe(['format' => 'markdown', 'pretty' => true])
            ->and($validatePreferences)->toBe(['format' => 'markdown', 'pretty' => true])
            ->and($viewPreferences)->toBe(['format' => 'markdown', 'pretty' => true])
            ->and($jsonRender)->toBe('{"status":"ok"}');
    } finally {
        removeDirectory($cwd);
    }
});

it('covers raw command and cleanup helpers through private application methods', function (): void {
    $cwd = makeTempDirectory('sift-application-raw-helper-');

    try {
        createProjectTool($cwd, 'dummy.php', "<?php\n");
        $application = makeTestApplication(new ToolRegistry([
            new DummyExecutableToolAdapter(new ToolLocator(PHP_BINARY)),
        ]));
        $tool = new DummyExecutableToolAdapter(new ToolLocator(PHP_BINARY));
        $tempFile = tempnam(sys_get_temp_dir(), 'sift-app-cleanup-');

        if ($tempFile === false) {
            throw new RuntimeException('Unable to allocate temporary file for cleanup testing.');
        }

        $prepared = invokeApplicationMethod($application, 'prepareRawCommand', [
            $cwd,
            $tool,
            ['--ansi'],
            [
                'enabled' => true,
                'toolBinary' => 'vendor/bin/dummy.php',
                'defaultArgs' => [],
                'blockedArgs' => [],
            ],
        ]);

        expect($prepared)->toBeInstanceOf(PreparedCommand::class)
            ->and($prepared->command[0] ?? null)->toBe(PHP_BINARY)
            ->and(str_replace('\\', '/', (string) ($prepared->command[1] ?? '')))->toContain('vendor/bin/dummy.php')
            ->and($prepared->command)->toContain('--ansi');

        expect(fn () => invokeApplicationMethod($application, 'prepareRawCommand', [
            $cwd,
            $tool,
            [],
            [
                'enabled' => true,
                'toolBinary' => 'vendor/bin/missing.php',
                'defaultArgs' => [],
                'blockedArgs' => [],
            ],
        ]))->toThrow(UserFacingException::class);

        invokeApplicationMethod($application, 'cleanupTempFiles', [
            new PreparedCommand(['dummy'], $cwd, metadata: [
                'temp_files' => [$tempFile, '', 123, $cwd.DIRECTORY_SEPARATOR.'missing.tmp'],
            ]),
        ]);

        expect($tempFile)->not->toBeFile();
    } finally {
        removeDirectory($cwd);
    }
});

it('runs the static application entry point for success and structured errors', function (): void {
    $version = runApplicationEntryPoint(['sift', 'version']);
    $failure = runApplicationEntryPoint(['sift', 'unknown-tool']);

    expect($version->getExitCode())->toBe(0)
        ->and(decodeJsonOutput($version)['version'])->toBe(Sift::VERSION)
        ->and($failure->getExitCode())->toBe(1)
        ->and(decodeJsonOutput($failure)['error']['code'])->toBe('unsupported_tool');
});

it('streams passthrough output through the static application entry point', function (): void {
    $cwd = makeTempDirectory('sift-application-entry-raw-');

    try {
        createProjectTool($cwd, 'composer', <<<'PHP'
<?php

declare(strict_types=1);

fwrite(STDOUT, "composer-raw-stdout\n");
fwrite(STDERR, "composer-raw-stderr\n");
exit(4);
PHP);
        writeSiftConfig($cwd, [
            'history' => ['enabled' => false],
            'output' => ['format' => 'json', 'size' => 'normal', 'pretty' => false],
            'tools' => [
                'composer' => [
                    'enabled' => true,
                    'toolBinary' => 'vendor/bin/composer',
                ],
            ],
        ]);

        $process = runApplicationEntryPoint(['sift', '--raw', 'composer', 'show'], $cwd);

        expect($process->getExitCode())->toBe(4)
            ->and($process->getOutput())->toBe("composer-raw-stdout\n")
            ->and($process->getErrorOutput())->toBe("composer-raw-stderr\n");
    } finally {
        removeDirectory($cwd);
    }
});

it('executes tools with process tracing enabled from config', function (): void {
    $cwd = makeTempDirectory('sift-application-show-process-');

    try {
        createProjectTool($cwd, 'dummy.php', <<<'PHP'
<?php

declare(strict_types=1);

fwrite(STDOUT, "step one\n");
fwrite(STDOUT, "step two\n");
exit(0);
PHP);
        writeSiftConfig($cwd, [
            'history' => ['enabled' => false],
            'output' => [
                'format' => 'json',
                'size' => 'normal',
                'pretty' => false,
                'show_process' => true,
            ],
            'tools' => [
                'dummy' => [
                    'enabled' => true,
                    'toolBinary' => 'vendor/bin/dummy.php',
                ],
            ],
        ]);
        $application = makeTestApplication(new ToolRegistry([
            new DummyExecutableToolAdapter(new ToolLocator(PHP_BINARY)),
        ]));

        $payload = runApplicationInDirectory($cwd, static fn () => $application->handle(['dummy']));

        expect($payload['status'])->toBe('passed')
            ->and($payload['summary']['exit_code'])->toBe(0)
            ->and($payload['_format'])->toBe('json')
            ->and($payload['_pretty'])->toBeFalse();
    } finally {
        removeDirectory($cwd);
    }
});

it('supports non-interactive add selection by number through stdin', function (): void {
    $cwd = makeTempDirectory('sift-application-add-stdin-number-');

    try {
        createProjectTool($cwd, 'composer', "<?php\n");

        $process = runApplicationEntryPoint(['sift', 'add'], $cwd, "1\n");
        $config = json_decode((string) file_get_contents($cwd.DIRECTORY_SEPARATOR.'sift.json'), true, flags: JSON_THROW_ON_ERROR);

        expect($process->getExitCode())->toBe(0)
            ->and(decodeJsonOutput($process)['status'])->toBe('added')
            ->and($config['tools']['composer']['toolBinary'])->toBe('composer');
    } finally {
        removeDirectory($cwd);
    }
});

it('supports non-interactive add selection by tool name through stdin', function (): void {
    $cwd = makeTempDirectory('sift-application-add-stdin-name-');

    try {
        createProjectTool($cwd, 'composer', "<?php\n");

        $process = runApplicationEntryPoint(['sift', 'add'], $cwd, "composer\n");
        $config = json_decode((string) file_get_contents($cwd.DIRECTORY_SEPARATOR.'sift.json'), true, flags: JSON_THROW_ON_ERROR);

        expect($process->getExitCode())->toBe(0)
            ->and(decodeJsonOutput($process)['tool'])->toBe('composer')
            ->and($config['tools']['composer']['toolBinary'])->toBe('composer');
    } finally {
        removeDirectory($cwd);
    }
});

it('returns invalid usage for unsupported add selections from stdin', function (): void {
    $cwd = makeTempDirectory('sift-application-add-stdin-invalid-');

    try {
        createProjectTool($cwd, 'composer', "<?php\n");

        $process = runApplicationEntryPoint(['sift', 'add'], $cwd, "invalid\n");

        expect($process->getExitCode())->toBe(1)
            ->and(decodeJsonOutput($process)['error']['code'])->toBe('invalid_usage');
    } finally {
        removeDirectory($cwd);
    }
});

it('covers add resolution and process output helpers through private application methods', function (): void {
    $cwd = makeTempDirectory('sift-application-add-helper-');

    try {
        $application = makeTestApplication(new ToolRegistry);
        $explicit = invokeApplicationMethod($application, 'resolveAddToolName', [$cwd, 'dummy', ['tools' => []]]);

        expect($explicit)->toBe('dummy');
        expect(fn () => invokeApplicationMethod($application, 'resolveAddToolName', [$cwd, null, ['tools' => []]]))
            ->toThrow(UserFacingException::class);

        expect(invokeApplicationMethod($application, 'hasInteractiveInput', []))->toBeFalse()
            ->and(invokeApplicationMethod($application, 'canRenderInteractivePrompt', []))->toBeFalse();

        invokeApplicationMethod($application, 'recordProcessOutput', ["one\r\ntwo\nthree"]);

        expect(getApplicationProperty($application, 'processLines'))->toBe(['one', 'two'])
            ->and(getApplicationProperty($application, 'processLineRemainder'))->toBe('three')
            ->and(getApplicationProperty($application, 'renderedProcessHeight'))->toBe(2);

        $callback = invokeApplicationMethod($application, 'processOutputCallback', []);
        $callback('stdout', "\nfour\n");

        expect(getApplicationProperty($application, 'processLines'))->toBe(['one', 'two', 'three', 'four']);

        invokeApplicationMethod($application, 'clearProcessOutput', []);

        expect(getApplicationProperty($application, 'processLines'))->toBe([])
            ->and(getApplicationProperty($application, 'processLineRemainder'))->toBe('')
            ->and(getApplicationProperty($application, 'renderedProcessHeight'))->toBe(0);
    } finally {
        removeDirectory($cwd);
    }
});

it('builds the default application registry with all supported tools', function (): void {
    $application = (new ApplicationFactory)->createDefault();
    $registry = getApplicationProperty($application, 'toolRegistry');

    expect($registry)->toBeInstanceOf(ToolRegistry::class)
        ->and($registry->names())->toBe([
            'composer-audit',
            'composer',
            'paratest',
            'pest',
            'phpcs',
            'pint',
            'rector',
            'psalm',
            'phpstan',
            'phpunit',
        ]);
});

function makeTestApplication(ToolRegistry $registry): Application
{
    $toolLocator = new ToolLocator(PHP_BINARY);
    $configLoader = new ConfigLoader;
    $configDocumentManager = new ConfigDocumentManager($configLoader);
    $runStore = new FileRunStore;

    return new Application(
        new OptionParser,
        $registry,
        new JsonRenderer,
        new MarkdownRenderer,
        new ProcessExecutor,
        new ResultPayloadFactory,
        new ResultMetaStamper,
        $configLoader,
        $runStore,
        new InitService($toolLocator, $configDocumentManager),
        new AddService($toolLocator, $configDocumentManager),
        new PolicyRunner([
            new ToolEnabledPolicy,
            new BlockedArgumentsPolicy,
            new ToolInstalledPolicy($toolLocator),
            new ComposerCommandPolicy,
            new RectorCommandPolicy,
        ]),
        $toolLocator,
        new ProjectInspector($toolLocator),
        new ValidateService($configLoader),
        new ViewService($runStore),
    );
}

/**
 * @template T
 * @param  callable(): T  $callback
 * @return T
 */
function runApplicationInDirectory(string $cwd, callable $callback): mixed
{
    $previous = getcwd();

    if ($previous === false) {
        throw new RuntimeException('Unable to resolve the current working directory.');
    }

    chdir($cwd);

    try {
        return $callback();
    } finally {
        chdir($previous);
    }
}

/**
 * @param  list<mixed>  $arguments
 */
function invokeApplicationMethod(Application $application, string $method, array $arguments): mixed
{
    $closure = Closure::bind(
        function (array $arguments) use ($method): mixed {
            return $this->{$method}(...$arguments);
        },
        $application,
        $application,
    );

    return $closure($arguments);
}

function getApplicationProperty(Application $application, string $property): mixed
{
    $closure = Closure::bind(
        function () use ($property): mixed {
            return $this->{$property};
        },
        $application,
        $application,
    );

    return $closure();
}


function runApplicationEntryPoint(array $argv, ?string $cwd = null, ?string $input = null): Symfony\Component\Process\Process
{
    $scriptPath = tempnam(sys_get_temp_dir(), 'sift-application-run-');

    if ($scriptPath === false) {
        throw new RuntimeException('Unable to allocate application runner script.');
    }

    $argvExport = var_export($argv, true);
    $cwdExport = var_export($cwd ?? siftRoot(), true);
    $autoloadPath = var_export(siftRoot().DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php', true);

    file_put_contents($scriptPath, <<<PHP
<?php

declare(strict_types=1);

require $autoloadPath;

chdir($cwdExport);

exit(Sift\\Console\\Application::run($argvExport));
PHP);

    $process = new Symfony\Component\Process\Process([PHP_BINARY, $scriptPath], env: siftTestEnvironment());
    $process->setInput($input);
    $process->run();
    @unlink($scriptPath);

    return $process;
}
