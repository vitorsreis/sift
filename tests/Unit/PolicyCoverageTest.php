<?php

declare(strict_types=1);

use Sift\Exceptions\UserFacingException;
use Sift\Runtime\BlockedArgumentsPolicy;
use Sift\Runtime\ComposerCommandPolicy;
use Sift\Runtime\RectorCommandPolicy;
use Sift\Runtime\ToolInstalledPolicy;
use Sift\Runtime\ToolLocator;
use Tests\Support\FakeToolAdapter;

it('rejects blocked arguments including option assignments', function (): void {
    $policy = new BlockedArgumentsPolicy;
    $tool = new FakeToolAdapter('demo', 'Install demo.', ['vendor/bin/demo']);

    expect(fn () => $policy->apply('.', $tool, ['--memory-limit=2G'], [
        'blockedArgs' => ['--memory-limit'],
    ]))->toThrow(UserFacingException::class);
});

it('allows only supported read only composer subcommands', function (): void {
    $policy = new ComposerCommandPolicy;
    $tool = new FakeToolAdapter('composer', 'Install composer.', ['vendor/bin/composer']);

    expect(fn () => $policy->apply('.', $tool, ['licenses'], []))->not->toThrow(UserFacingException::class)
        ->and(fn () => $policy->apply('.', $tool, ['update'], []))->toThrow(UserFacingException::class)
        ->and(fn () => $policy->apply('.', $tool, ['--ansi'], []))->toThrow(UserFacingException::class);
});

it('allows rector process dry run only', function (): void {
    $policy = new RectorCommandPolicy;
    $tool = new FakeToolAdapter('rector', 'Install rector.', ['vendor/bin/rector']);

    expect(fn () => $policy->apply('.', $tool, ['process', '--dry-run'], []))->not->toThrow(UserFacingException::class)
        ->and(fn () => $policy->apply('.', $tool, ['process'], []))->toThrow(UserFacingException::class)
        ->and(fn () => $policy->apply('.', $tool, ['list'], []))->toThrow(UserFacingException::class);
});

it('requires tools to be installed before execution', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, 'demo', "<?php\n");
        $policy = new ToolInstalledPolicy(new ToolLocator(PHP_BINARY));
        $tool = new FakeToolAdapter('demo', 'Install demo.', ['vendor/bin/demo']);

        expect(fn () => $policy->apply($cwd, $tool, [], ['toolBinary' => null]))->not->toThrow(UserFacingException::class)
            ->and(fn () => $policy->apply($cwd, new FakeToolAdapter('missing', 'Install missing.', ['vendor/bin/missing']), [], ['toolBinary' => null]))
            ->toThrow(UserFacingException::class);
    } finally {
        removeDirectory($cwd);
    }
});
