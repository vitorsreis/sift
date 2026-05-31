<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\Mago\MagoFormatParser;
use Tests\Support\FixtureProject;

it('normalizes mago format changed files from json output', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $report = (new MagoFormatParser())->parse(json_encode([
        'changed_files' => [$source],
        'parse_errors' => [],
    ], JSON_THROW_ON_ERROR), '', $project->root());

    expect($report->findings())->toBe(1);
    expect($report->summary())->toBe([
        'changed_files' => 1,
        'parse_errors' => 0,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::ChangedFile->value,
            'file' => 'src/Checkout.php',
        ],
    ]);
});

it('normalizes mago format changed files from dry-run diff output', function (): void {
    $project = FixtureProject::create();
    $report = (new MagoFormatParser())->parse(
        "diff --git a/src/Checkout.php b/src/Checkout.php\n--- a/src/Checkout.php\n+++ b/src/Checkout.php\n@@\n-old\n+new\nFound 1 file(s) that need formatting.",
        '',
        $project->root(),
    );

    expect($report->findings())->toBe(1);
    expect($report->items())->toBe([
        [
            'type' => ItemType::ChangedFile->value,
            'file' => 'src/Checkout.php',
        ],
    ]);
});
