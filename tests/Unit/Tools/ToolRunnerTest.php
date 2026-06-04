<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;
use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\PhpCommandFactory;
use Sift\Execution\PhpRuntimeArguments;
use Sift\Execution\ProcessRunner;
use Sift\Execution\ProcessSupervisor;
use Sift\Registry\ToolRegistry;
use Sift\Safety\Policy;
use Sift\Safety\PolicyPipeline;
use Sift\Safety\PolicyViolation;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\CliArguments;
use Sift\Tools\ToolContext;
use Sift\Tools\ToolRunner;
use Tests\Support\FixtureProject;

it('runs a normalized tool through registry, policies, process and parser', function (): void {
    $code = 'fwrite(STDOUT, "normalized-out"); fwrite(STDERR, "normalized-err");';
    $runner = new ToolRunner(
        registry: new ToolRegistry(toolRunnerAdapter('demo', ['demo-alias'], ['-r', $code])),
    );

    $result = $runner->run(
        arguments: new CliArguments('demo-alias', ['user-arg']),
        config: toolRunnerConfig(new ToolConfig('demo', true, null, [], 30)),
        cwd: getcwd() ?: '.',
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['tool'])->toBe('demo');
    expect($payload['status'])->toBe('passed');
    expect($payload['summary'])->toBe([
        'stdout' => 'normalized-out',
        'stderr' => 'normalized-err',
        'parsed_command' => [PHP_BINARY, '-r', $code, 'user-arg'],
    ]);
    expect($payload['meta']['exit_code'])->toBe(0);
    expect($payload['meta']['command'])->toBe([PHP_BINARY, '-r', $code, 'user-arg']);
});

it('runs policies before starting the process', function (): void {
    $project = FixtureProject::create();
    $marker = $project->path('ran.txt');
    $code = 'file_put_contents(' . var_export($marker, true) . ', "ran");';
    $runner = new ToolRunner(
        registry: new ToolRegistry(toolRunnerAdapter('demo', [], ['-r', $code])),
        policyPipeline: new PolicyPipeline([
            new class implements Policy {
                /**
                 * @return PolicyViolation[]
                 */
                public function violations(PreparedCommand $command, ToolContext $context, ToolConfig $config): array
                {
                    return [new PolicyViolation(ErrorCode::PolicyBlocked, 'Blocked.', 'test_policy')];
                }
            },
        ]),
    );

    try {
        $runner->run(
            arguments: new CliArguments('demo'),
            config: toolRunnerConfig(new ToolConfig('demo', true, null, [], 30)),
            cwd: $project->root(),
        );
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::PolicyBlocked);
        expect(is_file($marker))->toBeFalse();

        return;
    }

    throw new RuntimeException('Tool runner did not block execution.');
});

it('passes php ini settings to php composer proxy binaries and lets tool values override inherited values', function (): void {
    $project = FixtureProject::create();
    $proxy = $project->write('vendor/bin/demo', <<<'PHP'
#!/usr/bin/env php
<?php
fwrite(STDOUT, ini_get('memory_limit') . '|' . implode(',', array_slice($_SERVER['argv'], 1)));
PHP);
    $batch = $project->write('vendor/bin/demo.bat', '@echo off');
    $runner = new ToolRunner(
        registry: new ToolRegistry(toolRunnerAdapter('demo', [], [])),
        phpCommandFactory: new PhpCommandFactory(PHP_BINARY, new PhpRuntimeArguments([
            PHP_BINARY,
            '-dmemory_limit=64M',
            '-dxdebug.mode=off',
            $project->path('bin/sift'),
            'demo',
            'user-arg',
        ])),
    );

    $result = $runner->run(
        arguments: new CliArguments('demo', ['user-arg'], ['d' => [
            'zend_extension=xdebug',
            'xdebug.mode=coverage',
            'memory_limit=128M',
        ]]),
        config: toolRunnerConfig(new ToolConfig('demo', true, $batch, [], 30)),
        cwd: $project->root(),
    );

    if (! $result instanceof NormalizedResult) {
        throw new RuntimeException('Expected normalized result.');
    }

    $payload = $result->toPayload();

    expect($payload['summary'])->toMatchArray([
        'stdout' => '128M|user-arg',
        'parsed_command' => [
            PHP_BINARY,
            '-dzend_extension=xdebug',
            '-dxdebug.mode=coverage',
            '-dmemory_limit=128M',
            $proxy,
            'user-arg',
        ],
    ]);
    expect($payload['meta']['command'])->toBe([
        PHP_BINARY,
        '-dzend_extension=xdebug',
        '-dxdebug.mode=coverage',
        '-dmemory_limit=128M',
        $proxy,
        'user-arg',
    ]);
});

it('returns interrupted executions without parsing partial output', function (): void {
    $runner = new ToolRunner(
        registry: new ToolRegistry(toolRunnerAdapter('demo', [], ['-r', 'sleep(3);'])),
        processRunner: new ProcessRunner(new ProcessSupervisor(interruptionChecker: static fn(): bool => true)),
    );

    $result = $runner->run(
        arguments: new CliArguments('demo'),
        config: toolRunnerConfig(new ToolConfig('demo', true, null, [], 30)),
        cwd: getcwd() ?: '.',
    );

    if (! $result instanceof ExecutionResult) {
        throw new RuntimeException('Expected execution result.');
    }

    expect($result->interrupted())->toBeTrue();
    expect($result->exitCode())->toBe(130);
    expect($result->errorCode())->toBe(ErrorCode::ProcessInterrupted);
});

/**
 * @param  list<string>  $aliases
 * @param  list<string>  $defaultArguments
 */
function toolRunnerAdapter(string $name, array $aliases, array $defaultArguments): AbstractCliToolAdapter
{
    return new readonly class ($name, $aliases, $defaultArguments) extends AbstractCliToolAdapter {
        /**
         * @param  list<string>  $aliases
         * @param  list<string>  $defaultArguments
         */
        public function __construct(
            private string $name,
            private array $aliases,
            private array $defaultArguments,
        ) {}

        protected function name(): string
        {
            return $this->name;
        }

        protected function aliases(): array
        {
            return $this->aliases;
        }

        protected function description(): string
        {
            return 'Demo adapter.';
        }

        protected function binaryCandidates(): array
        {
            return [PHP_BINARY];
        }

        protected function installHint(): string
        {
            return 'PHP is required.';
        }

        protected function defaultContext(): string
        {
            return 'demo';
        }

        protected function defaultArguments(ToolContext $context): array
        {
            return $this->defaultArguments;
        }

        public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
        {
            return NormalizedResult::passed($context->toolName(), [
                'stdout' => $execution->stdout(),
                'stderr' => $execution->stderr(),
                'parsed_command' => $command->argv(),
            ]);
        }
    };
}

function toolRunnerConfig(ToolConfig ...$tools): SiftConfig
{
    $indexedTools = [];

    foreach ($tools as $tool) {
        $indexedTools[$tool->name()] = $tool;
    }

    return new SiftConfig(
        schema: 'https://raw.githubusercontent.com/vitorsreis/sift/master/resources/schema.json',
        configPath: null,
        usingDefaults: true,
        history: new HistoryConfig(false, '.sift/history', 50, 30, 1048576, true),
        output: new OutputConfig('compact', false, false),
        tools: $indexedTools,
    );
}
