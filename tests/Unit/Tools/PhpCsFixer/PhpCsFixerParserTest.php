<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\PhpCsFixer\PhpCsFixerParser;
use Tests\Support\FixtureProject;

it('normalizes php-cs-fixer json files, fixers and diffs', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $report = (new PhpCsFixerParser())->parse(json_encode([
        'about' => 'PHP CS Fixer 3.95.3',
        'files' => [
            [
                'name' => $source,
                'appliedFixers' => ['ordered_imports', 'blank_line_after_opening_tag'],
                'diff' => "--- Original\n+++ New\n@@\n-<?php\n+<?php\n\n",
            ],
        ],
        'time' => ['total' => 0.123],
        'memory' => 12.5,
    ], JSON_THROW_ON_ERROR), '', $project->root());

    expect($report->files())->toBe(1);
    expect($report->fixers())->toBe(2);
    expect($report->diffs())->toBe(1);
    expect($report->summary())->toBe([
        'files' => 1,
        'fixers' => 2,
        'diffs' => 1,
        'time_total' => 0.123,
        'memory' => 12.5,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::ChangedFile->value,
            'file' => 'src/Checkout.php',
            'applied_fixers' => ['ordered_imports', 'blank_line_after_opening_tag'],
        ],
        [
            'type' => ItemType::Diff->value,
            'file' => 'src/Checkout.php',
            'diff' => "--- Original\n+++ New\n@@\n-<?php\n+<?php\n\n",
            'applied_fixers' => ['ordered_imports', 'blank_line_after_opening_tag'],
        ],
    ]);
});
