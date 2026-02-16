<?php

declare(strict_types=1);

use Sift\Console\Application;
use Sift\Console\OptionParser;
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
        ->and($help['options'])->toContain('--raw')
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
