<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\CliArguments;
use Sift\Tools\MutationPolicy;
use Sift\Tools\ToolContext;

it('builds a tool definition from adapter metadata', function (): void {
    $definition = testCliAdapter()->definition();

    expect($definition->name())->toBe('pint');
    expect($definition->aliases())->toBe(['format']);
    expect($definition->description())->toBe('Laravel Pint formatter.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/pint.bat', 'vendor/bin/pint', 'pint']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev laravel/pint');
    expect($definition->defaultContext())->toBe('style');
    expect($definition->mutationPolicy())->toBe(MutationPolicy::RepairFlag);
    expect($definition->repairCommand())->toBe(['--write']);
});

it('builds context from cli arguments and sift options', function (): void {
    $context = testCliAdapter()->context(
        arguments: new CliArguments(
            tool: 'format',
            toolArguments: ['--filter', 'UserTest', '--coverage', '--dry-run', 'src'],
            siftOptions: ['raw' => true, 'debug' => true],
        ),
        cwd: '/repo',
    );

    expect($context->toolName())->toBe('pint');
    expect($context->userArgs())->toBe(['--filter', 'UserTest', '--coverage', '--dry-run', 'src']);
    expect($context->cwd())->toBe('/repo');
    expect($context->raw())->toBeTrue();
    expect($context->debug())->toBeTrue();
    expect($context->filter())->toBe('UserTest');
    expect($context->coverage())->toBeTrue();
    expect($context->dryRun())->toBeTrue();
});

it('prepares a base command with defaults, user arguments, cwd and timeout', function (): void {
    $adapter = testCliAdapter(defaultArguments: ['--test']);
    $context = $adapter->context(new CliArguments('pint', ['src']), '/repo');

    $command = $adapter->prepare(
        tool: new LocatedTool('pint', '/repo/vendor/bin/pint', 'vendor/bin/pint', 'relative'),
        context: $context,
        config: new ToolConfig('pint', true, null, [], 120),
    );

    expect($command->tool())->toBe('pint');
    expect($command->binary())->toBe('/repo/vendor/bin/pint');
    expect($command->arguments())->toBe(['--test', 'src']);
    expect($command->cwd())->toBe('/repo');
    expect($command->timeout())->toBe(120);
});

it('consumes repair pseudo flag only for compatible adapters', function (): void {
    $repairAdapter = testCliAdapter(
        mutationPolicy: MutationPolicy::RepairFlag,
        repairCommand: ['--write'],
    );
    $repairContext = $repairAdapter->context(new CliArguments('pint', ['--repair', 'src']), '/repo');
    $repairCommand = $repairAdapter->prepare(
        tool: new LocatedTool('pint', '/repo/vendor/bin/pint', 'vendor/bin/pint', 'relative'),
        context: $repairContext,
        config: new ToolConfig('pint', true, null, [], 120),
    );

    expect($repairContext->repair())->toBeTrue();
    expect($repairContext->userArgs())->toBe(['src']);
    expect($repairCommand->arguments())->toBe(['--write', 'src']);

    $safeAdapter = testCliAdapter(mutationPolicy: MutationPolicy::Never);
    $safeContext = $safeAdapter->context(new CliArguments('pint', ['--repair', 'src']), '/repo');
    $safeCommand = $safeAdapter->prepare(
        tool: new LocatedTool('pint', '/repo/vendor/bin/pint', 'vendor/bin/pint', 'relative'),
        context: $safeContext,
        config: new ToolConfig('pint', true, null, [], 120),
    );

    expect($safeContext->repair())->toBeFalse();
    expect($safeContext->userArgs())->toBe(['--repair', 'src']);
    expect($safeCommand->arguments())->toBe(['--repair', 'src']);
});

it('exposes shared adapter hooks for config machine output meta and status', function (): void {
    $adapter = testCliAdapter();
    $context = $adapter->context(new CliArguments('pint', ['--filter=UserTest', '--coverage', '--dry-run']), '/repo');

    expect($adapter->exposedInitialConfig())->toBe([]);
    expect($adapter->exposedMachineOutputRules())->toBe([]);
    expect($adapter->exposedCommonMeta($context))->toMatchArray([
        'filter' => 'UserTest',
        'coverage' => true,
        'dry_run' => true,
    ]);
    expect($adapter->exposedResolveStatus(ExecutionResult::completed(0, '', '', 0.1))->value)->toBe('passed');
    expect($adapter->exposedResolveStatus(ExecutionResult::completed(1, '', '', 0.1))->value)->toBe('error');
});

abstract readonly class TestExposedCliAdapter extends AbstractCliToolAdapter
{
    /**
     * @return array<string, mixed>
     */
    public function exposedInitialConfig(): array
    {
        return $this->initialConfig();
    }

    /**
     * @return list<string>
     */
    public function exposedMachineOutputRules(): array
    {
        return $this->machineOutputRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function exposedCommonMeta(ToolContext $context): array
    {
        return $this->commonMeta($context);
    }

    public function exposedResolveStatus(ExecutionResult $execution): RunStatus
    {
        return $this->resolveStatus($execution);
    }
}

/**
 * @param  list<string>  $defaultArguments
 * @param  list<string>  $repairCommand
 */
function testCliAdapter(
    array $defaultArguments = [],
    MutationPolicy $mutationPolicy = MutationPolicy::RepairFlag,
    array $repairCommand = ['--write'],
): TestExposedCliAdapter {
    return new readonly class ($defaultArguments, $mutationPolicy, $repairCommand) extends TestExposedCliAdapter {
        /**
         * @param  list<string>  $defaultArguments
         * @param  list<string>  $repairCommand
         */
        public function __construct(
            private array $defaultArguments,
            private MutationPolicy $mutationPolicy,
            private array $repairCommand,
        ) {}

        protected function name(): string
        {
            return 'pint';
        }

        protected function aliases(): array
        {
            return ['format'];
        }

        protected function description(): string
        {
            return 'Laravel Pint formatter.';
        }

        protected function binaryCandidates(): array
        {
            return ['vendor/bin/pint.bat', 'vendor/bin/pint', 'pint'];
        }

        protected function installHint(): string
        {
            return 'composer require --dev laravel/pint';
        }

        protected function defaultContext(): string
        {
            return 'style';
        }

        protected function versionCommand(): array
        {
            return ['--version'];
        }

        protected function mutationPolicy(): MutationPolicy
        {
            return $this->mutationPolicy;
        }

        protected function repairCommand(): array
        {
            return $this->repairCommand;
        }

        protected function defaultArguments(ToolContext $context): array
        {
            return $this->defaultArguments;
        }

        public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
        {
            return NormalizedResult::passed($context->toolName());
        }
    };
}
