<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Registry\ToolRegistry;
use Sift\Tools\CliArguments;
use Sift\Tools\Composer\ComposerToolAdapter;
use Sift\Tools\ComposerRequireChecker\ComposerRequireCheckerToolAdapter;
use Sift\Tools\ComposerUnused\ComposerUnusedToolAdapter;
use Sift\Tools\Deptrac\DeptracToolAdapter;
use Sift\Tools\Infection\InfectionToolAdapter;
use Sift\Tools\Mago\MagoToolAdapter;
use Sift\Tools\ParallelLint\ParallelLintToolAdapter;
use Sift\Tools\PhpCs\PhpcbfToolAdapter;
use Sift\Tools\PhpCs\PhpcsToolAdapter;
use Sift\Tools\PhpCsFixer\PhpCsFixerToolAdapter;
use Sift\Tools\PhpMd\PhpmdToolAdapter;
use Sift\Tools\PhpStan\PhpstanToolAdapter;
use Sift\Tools\Pint\PintToolAdapter;
use Sift\Tools\Psalm\PsalmToolAdapter;
use Sift\Tools\Rector\RectorToolAdapter;
use Sift\Tools\Testing\ParatestToolAdapter;
use Sift\Tools\Testing\PestToolAdapter;
use Sift\Tools\Testing\PhpunitToolAdapter;
use Sift\Tools\ToolAdapter;
use Sift\Tools\ToolContext;
use Sift\Tools\ToolDefinition;

it('finds adapters by canonical name and alias', function (): void {
    $adapter = testRegistryAdapter(new ToolDefinition(
        name: 'pest',
        aliases: ['test', 'tests'],
        description: 'Pest test runner.',
        binaryCandidates: ['vendor/bin/pest.bat', 'vendor/bin/pest', 'pest'],
        installHint: 'composer require --dev pestphp/pest',
        defaultContext: 'test',
    ));

    $registry = new ToolRegistry($adapter);

    expect($registry->find('pest'))->toBe($adapter);
    expect($registry->find('test'))->toBe($adapter);
    expect($registry->find('TESTS'))->toBe($adapter);
    expect($registry->find('missing'))->toBeNull();
});

it('keeps adapters in registration order', function (): void {
    $pest = testRegistryAdapter(new ToolDefinition(
        name: 'pest',
        aliases: ['test'],
        description: 'Pest test runner.',
        binaryCandidates: ['pest'],
        installHint: 'composer require --dev pestphp/pest',
        defaultContext: 'test',
    ));
    $phpstan = testRegistryAdapter(new ToolDefinition(
        name: 'phpstan',
        aliases: ['analyse'],
        description: 'PHPStan static analyzer.',
        binaryCandidates: ['phpstan'],
        installHint: 'composer require --dev phpstan/phpstan',
        defaultContext: 'analysis',
    ));

    expect((new ToolRegistry($pest, $phpstan))->all())->toBe([$pest, $phpstan]);
});

it('registers built-in adapters without external discovery', function (): void {
    $registry = ToolRegistry::builtIns();

    expect($registry->all())->toHaveCount(18);
    expect($registry->find('pest'))->toBeInstanceOf(PestToolAdapter::class);
    expect($registry->find('test'))->toBeInstanceOf(PestToolAdapter::class);
    expect($registry->find('phpunit'))->toBeInstanceOf(PhpunitToolAdapter::class);
    expect($registry->find('paratest'))->toBeInstanceOf(ParatestToolAdapter::class);
    expect($registry->find('phpstan'))->toBeInstanceOf(PhpstanToolAdapter::class);
    expect($registry->find('psalm'))->toBeInstanceOf(PsalmToolAdapter::class);
    expect($registry->find('phpcs'))->toBeInstanceOf(PhpcsToolAdapter::class);
    expect($registry->find('phpcbf'))->toBeInstanceOf(PhpcbfToolAdapter::class);
    expect($registry->find('rector'))->toBeInstanceOf(RectorToolAdapter::class);
    expect($registry->find('pint'))->toBeInstanceOf(PintToolAdapter::class);
    expect($registry->find('mago'))->toBeInstanceOf(MagoToolAdapter::class);
    expect($registry->find('infection'))->toBeInstanceOf(InfectionToolAdapter::class);
    expect($registry->find('deptrac'))->toBeInstanceOf(DeptracToolAdapter::class);
    expect($registry->find('php-cs-fixer'))->toBeInstanceOf(PhpCsFixerToolAdapter::class);
    expect($registry->find('phpmd'))->toBeInstanceOf(PhpmdToolAdapter::class);
    expect($registry->find('composer-unused'))->toBeInstanceOf(ComposerUnusedToolAdapter::class);
    expect($registry->find('composer-require-checker'))->toBeInstanceOf(ComposerRequireCheckerToolAdapter::class);
    expect($registry->find('parallel-lint'))->toBeInstanceOf(ParallelLintToolAdapter::class);
    expect($registry->find('composer'))->toBeInstanceOf(ComposerToolAdapter::class);
    expect($registry->find('vendor/package-tool'))->toBeNull();
});

it('rejects duplicate adapter names and aliases', function (): void {
    $pest = testRegistryAdapter(new ToolDefinition(
        name: 'pest',
        aliases: ['test'],
        description: 'Pest test runner.',
        binaryCandidates: ['pest'],
        installHint: 'composer require --dev pestphp/pest',
        defaultContext: 'test',
    ));
    $phpunit = testRegistryAdapter(new ToolDefinition(
        name: 'phpunit',
        aliases: ['test'],
        description: 'PHPUnit test runner.',
        binaryCandidates: ['phpunit'],
        installHint: 'composer require --dev phpunit/phpunit',
        defaultContext: 'test',
    ));

    expect(fn(): mixed => new ToolRegistry($pest, $phpunit))
        ->toThrow(InvalidArgumentException::class, 'Tool registry name "test" is already registered.');
});

function testRegistryAdapter(ToolDefinition $definition): ToolAdapter
{
    return new readonly class ($definition) implements ToolAdapter {
        public function __construct(
            private ToolDefinition $definition,
        ) {}

        public function definition(): ToolDefinition
        {
            return $this->definition;
        }

        public function context(CliArguments $arguments, string $cwd): ToolContext
        {
            return new ToolContext($arguments->tool(), cwd: $cwd);
        }

        public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
        {
            return new PreparedCommand($tool->tool(), $tool->binary(), timeout: $config->timeout());
        }

        public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
        {
            return NormalizedResult::passed($context->toolName());
        }
    };
}
