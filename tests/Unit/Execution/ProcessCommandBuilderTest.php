<?php

declare(strict_types=1);

use Sift\Core\PreparedCommand;
use Sift\Execution\Platform;
use Sift\Execution\ProcessCommandBuilder;

it('executes unix commands directly without shell wrapping', function (): void {
    $command = new PreparedCommand(
        tool: 'pest',
        binary: 'vendor/bin/pest',
        arguments: ['--filter', 'Checkout Test'],
        cwd: '/project',
    );

    $argv = (new ProcessCommandBuilder(new Platform('Linux')))->argv($command);

    expect($argv)->toBe(['vendor/bin/pest', '--filter', 'Checkout Test']);
});

it('executes windows exe commands directly', function (): void {
    $command = new PreparedCommand(
        tool: 'phpstan',
        binary: 'C:\\tools\\phpstan.exe',
        arguments: ['analyse', 'src'],
        cwd: 'C:\\project',
    );

    $argv = (new ProcessCommandBuilder(new Platform('Windows')))->argv($command);

    expect($argv)->toBe(['C:\\tools\\phpstan.exe', 'analyse', 'src']);
});

it('wraps windows batch commands through cmd exe', function (): void {
    $command = new PreparedCommand(
        tool: 'pint',
        binary: 'vendor\\bin\\pint.bat',
        arguments: ['--repair', 'app\\Models\\User.php'],
        cwd: 'C:\\project',
    );

    $argv = (new ProcessCommandBuilder(new Platform('Windows')))->argv($command);

    expect($argv)->toBe([
        'cmd.exe',
        '/d',
        '/c',
        'vendor\\bin\\pint.bat',
        '--repair',
        'app\\Models\\User.php',
    ]);
});

it('keeps windows batch arguments separated for proc open', function (): void {
    $command = new PreparedCommand(
        tool: 'tool',
        binary: 'vendor\\bin\\tool.cmd',
        arguments: ['A&B', 'has "quote"', ''],
        cwd: 'C:\\project',
    );

    $argv = (new ProcessCommandBuilder(new Platform('Windows')))->argv($command);

    expect($argv)->toBe([
        'cmd.exe',
        '/d',
        '/c',
        'vendor\\bin\\tool.cmd',
        'A&B',
        'has "quote"',
        '',
    ]);
});
