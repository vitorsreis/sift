<?php

declare(strict_types=1);

use Sift\Core\PreparedCommand;
use Sift\Execution\ProcessSupervisor;
use Tests\Support\FixtureProject;

if (PHP_OS_FAMILY === 'Windows') {
    it('passes windows batch metacharacters as argument data', function (): void {
        $project = FixtureProject::create();
        $helper = $project->write('argv.php', '<?php echo json_encode(array_slice($argv, 1), JSON_THROW_ON_ERROR);');
        $batch = $project->write('argv.cmd', sprintf("@echo off\r\n\"%s\" \"%s\" %%*\r\n", PHP_BINARY, $helper));
        $marker = $project->path('injected.txt');
        $arguments = [
            'A&B',
            'A|B',
            'A<B',
            'A>B',
            'A^B',
            'A%B',
            'A!B',
            'has "quote"',
            'has space',
            '& echo injected > ' . $marker,
            '',
        ];

        $result = (new ProcessSupervisor())->run(
            new PreparedCommand('batch-test', $batch, $arguments, $project->root()),
            10,
        );

        expect($result->exitCode())->toBe(0);
        expect(json_decode($result->stdout(), true, 512, JSON_THROW_ON_ERROR))->toBe($arguments);
        expect($marker)->not->toBeFile();
    });
}
