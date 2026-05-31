<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\ParallelLint\ParallelLintParser;
use Tests\Support\FixtureProject;

it('normalizes parallel-lint checked files and syntax errors', function (): void {
    $project = FixtureProject::create();
    $broken = $project->write('src/Broken.php', '<?php echo ;');
    $fatal = $project->write('src/Fatal.php', '<?php');
    $report = (new ParallelLintParser())->parse(json_encode([
        'phpVersion' => 80506,
        'hhvmVersion' => '',
        'parallelJobs' => 10,
        'results' => [
            'checkedFiles' => ['src/Good.php'],
            'filesWithSyntaxError' => [$broken],
            'skippedFiles' => ['vendor/skip.php'],
            'errors' => [
                [
                    'type' => 'syntaxError',
                    'file' => $broken,
                    'line' => 1,
                    'message' => 'Parse error: syntax error, unexpected token ";" in Broken.php on line 1',
                    'normalizeMessage' => 'Unexpected token ";".',
                    'blame' => null,
                ],
                [
                    'type' => 'error',
                    'file' => $fatal,
                    'message' => 'Could not lint file.',
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR), '', $project->root());

    expect($report->errors())->toBe(2);
    expect($report->summary())->toBe([
        'checked_files' => 1,
        'files_with_syntax_error' => 1,
        'skipped_files' => 1,
        'errors' => 2,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::SyntaxError->value,
            'file' => 'src/Broken.php',
            'line' => 1,
            'message' => 'Parse error: syntax error, unexpected token ";" in Broken.php on line 1',
            'normalized_message' => 'Unexpected token ";".',
        ],
        [
            'type' => ItemType::Error->value,
            'file' => 'src/Fatal.php',
            'message' => 'Could not lint file.',
        ],
    ]);
});
