<?php

declare(strict_types=1);

use Sift\Runtime\ToolLocator;

it('uses the current php binary for project scripts by default', function (): void {
    $cwd = makeTempDirectory();

    try {
        $path = createProjectTool($cwd, 'demo', "<?php\n");
        $resolved = (new ToolLocator)->locate($cwd, ['vendor/bin/demo']);

        expect($resolved)->not->toBeNull()
            ->and($resolved['command_prefix'] ?? null)->toBe([PHP_BINARY, $path]);
    } finally {
        removeDirectory($cwd);
    }
});

it('allows an explicit php binary override for project scripts', function (): void {
    $cwd = makeTempDirectory();

    try {
        $path = createProjectTool($cwd, 'demo', "<?php\n");
        $resolved = (new ToolLocator('D:\\php\\php.exe'))->locate($cwd, ['vendor/bin/demo']);

        expect($resolved)->not->toBeNull()
            ->and($resolved['command_prefix'] ?? null)->toBe(['D:\\php\\php.exe', $path]);
    } finally {
        removeDirectory($cwd);
    }
});

it('keeps batch tools directly executable', function (): void {
    $cwd = makeTempDirectory();

    try {
        $path = createProjectTool($cwd, 'demo.bat', "@echo off\r\n");
        $resolved = (new ToolLocator('D:\\php\\php.exe'))->locate($cwd, ['vendor/bin/demo.bat']);

        expect($resolved)->not->toBeNull()
            ->and($resolved['command_prefix'] ?? null)->toBe([$path]);
    } finally {
        removeDirectory($cwd);
    }
});

it('resolves PATH commands without wrapping them in php', function (): void {
    $locator = new ToolLocator('D:\\php\\php.exe');
    $command = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';

    $resolved = $locator->locate(sys_get_temp_dir(), [$command]);

    expect($resolved)->not->toBeNull()
        ->and($resolved['candidate'] ?? null)->toBe($command)
        ->and($resolved['command_prefix'] ?? null)->toBe([$command])
        ->and($resolved['path'] ?? null)->toBe($command);
});

it('returns null when no candidate can be resolved', function (): void {
    $cwd = makeTempDirectory();

    try {
        $resolved = (new ToolLocator)->locate($cwd, ['vendor/bin/missing-tool', 'definitely-missing-command']);

        expect($resolved)->toBeNull();
    } finally {
        removeDirectory($cwd);
    }
});
