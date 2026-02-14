<?php

declare(strict_types=1);

it('uses configured history path and max_files during tool execution and view commands', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, 'pint.php', <<<'PHP'
<?php

declare(strict_types=1);

echo json_encode([
    'result' => 'pass',
    'files' => [],
], JSON_THROW_ON_ERROR);
PHP);

        writeSiftConfig($cwd, [
            'history' => ['enabled' => true, 'max_files' => 1, 'path' => 'var/history'],
            'output' => ['format' => 'json', 'size' => 'normal', 'pretty' => true, 'show_process' => false],
            'tools' => [
                'pint' => [
                    'enabled' => true,
                    'toolBinary' => 'vendor/bin/pint.php',
                ],
            ],
        ], 'custom.sift.json');

        $first = runSift(['--config=custom.sift.json', '--format=json', '--pretty', 'pint'], $cwd);
        $second = runSift(['--config=custom.sift.json', '--format=json', '--pretty', 'pint'], $cwd);

        expect($first->getExitCode())->toBe(0)
            ->and($second->getExitCode())->toBe(0);

        $secondPayload = decodeJsonOutput($second);
        $listing = runSift(['--config=custom.sift.json', 'view', 'list', '--format=json', '--pretty'], $cwd);
        $files = glob($cwd.DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'history'.DIRECTORY_SEPARATOR.'*.json');

        expect($listing->getExitCode())->toBe(0);

        $listingPayload = decodeJsonOutput($listing);
        $storedRunIds = array_column($listingPayload['items'], 'run_id');

        expect($files)->toHaveCount(1)
            ->and($listingPayload['total'])->toBe(1)
            ->and($storedRunIds)->toBe([$secondPayload['run_id']]);
    } finally {
        removeDirectory($cwd);
    }
});
