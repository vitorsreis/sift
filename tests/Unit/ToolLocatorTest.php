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
