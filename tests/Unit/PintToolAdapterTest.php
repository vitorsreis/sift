<?php

declare(strict_types=1);

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Runtime\ToolLocator;
use Sift\Tools\PintToolAdapter;

it('parses failing pint json even when stdout contains leading noise', function (): void {
    $adapter = new PintToolAdapter(new ToolLocator);

    $result = $adapter->parse(
        new ExecutionResult(
            exitCode: 1,
            stdout: "Warning: extra output before json\n".json_encode([
                'result' => 'fail',
                'files' => [[
                    'path' => 'Bad.php',
                    'fixers' => ['class_definition', 'function_declaration'],
                ]],
            ], JSON_THROW_ON_ERROR),
            stderr: '',
            duration: 15,
        ),
        new PreparedCommand(
            command: ['pint', '--format=json', '--test'],
            cwd: sys_get_temp_dir(),
            metadata: ['mode' => 'test'],
        ),
        ['mode' => 'test'],
    );

    expect($result->status)->toBe('failed')
        ->and($result->summary)->toBe([
            'files' => 1,
            'fixers' => 2,
        ])
        ->and($result->items[0])->toBe([
            'file' => 'Bad.php',
            'fixers' => ['class_definition', 'function_declaration'],
        ]);
});
