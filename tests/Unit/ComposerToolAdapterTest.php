<?php

declare(strict_types=1);

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Runtime\ToolLocator;
use Sift\Tools\ComposerToolAdapter;

it('injects json format for supported composer subcommands', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, 'composer', <<<'PHP'
<?php

declare(strict_types=1);
PHP);

        $adapter = new ComposerToolAdapter(new ToolLocator);
        $prepared = $adapter->prepare($cwd, ['licenses'], [
            'tool_binary' => 'vendor/bin/composer',
            'subcommand' => 'licenses',
            'mode' => 'licenses',
        ]);

        expect($prepared->command[0] ?? null)->toBe(PHP_BINARY)
            ->and(str_replace('\\', '/', (string) ($prepared->command[1] ?? '')))->toContain('vendor/bin/composer')
            ->and($prepared->command)->toContain('licenses')
            ->and($prepared->command)->toContain('--format=json');
    } finally {
        removeDirectory($cwd);
    }
});

it('normalizes noisy composer show output with no dependencies', function (): void {
    $adapter = new ComposerToolAdapter(new ToolLocator);
    $result = $adapter->parse(
        new ExecutionResult(
            0,
            "[]\nNo dependencies installed. Try running composer install or update.\n",
            '',
            12,
        ),
        new PreparedCommand(
            ['composer', 'show', '--format=json'],
            siftRoot(),
            ['subcommand' => 'show', 'mode' => 'show'],
        ),
        ['subcommand' => 'show', 'mode' => 'show'],
    );

    expect($result->tool)->toBe('composer')
        ->and($result->status)->toBe('passed')
        ->and($result->summary)->toBe([
            'packages' => 0,
            'outdated' => 0,
            'abandoned' => 0,
        ])
        ->and($result->items)->toBe([])
        ->and($result->meta['subcommand'])->toBe('show');
});
