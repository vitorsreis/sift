<?php

declare(strict_types=1);

it('passes through raw tool output without rendering or history', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, 'pint.bat', "@echo off\r\necho RAW STDOUT %*\r\necho RAW STDERR %* 1>&2\r\nexit /b 3\r\n");
        writeSiftConfig($cwd, [
            'history' => ['enabled' => true],
            'output' => ['format' => 'markdown', 'size' => 'compact', 'pretty' => true],
            'tools' => [
                'pint' => [
                    'enabled' => true,
                    'toolBinary' => 'vendor/bin/pint.bat',
                ],
            ],
        ]);

        $process = runSift(['--raw', 'pint', 'src'], $cwd);

        expect($process->getExitCode())->toBe(3)
            ->and(str_replace("\r", '', $process->getOutput()))->toContain('RAW STDOUT src')
            ->and(str_replace("\r", '', $process->getErrorOutput()))->toContain('RAW STDERR src')
            ->and(trim($process->getOutput()))->not->toStartWith('{')
            ->and(is_dir($cwd.DIRECTORY_SEPARATOR.'.sift'.DIRECTORY_SEPARATOR.'history'))->toBeFalse();
    } finally {
        removeDirectory($cwd);
    }
});
