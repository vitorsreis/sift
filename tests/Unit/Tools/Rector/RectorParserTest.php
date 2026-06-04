<?php

declare(strict_types=1);

use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Sift\Core\ItemType;
use Sift\Tools\Rector\RectorParser;
use Tests\Support\FixtureProject;

it('normalizes rector changed files diffs and errors', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $report = (new RectorParser())->parse(json_encode([
        'totals' => [
            'changed_files' => 1,
            'errors' => 1,
        ],
        'changed_files' => [$source],
        'file_diffs' => [
            [
                'file' => $source,
                'diff' => "--- Original\n+++ New\n@@\n-old\n+new",
                'applied_rectors' => [
                    AddOverrideAttributeToOverriddenMethodsRector::class,
                ],
            ],
        ],
        'errors' => [
            [
                'message' => 'Could not parse file.',
                'file' => $source,
                'line' => 12,
                'caused_by' => 'Rector\\BrokenRector',
            ],
        ],
    ], JSON_THROW_ON_ERROR), '', $project->root());

    expect($report->changedFiles())->toBe(1);
    expect($report->errors())->toBe(1);
    expect($report->findings())->toBe(2);
    expect($report->summary())->toBe([
        'changed_files' => 1,
        'errors' => 1,
        'diffs' => 1,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::ChangedFile->value,
            'file' => 'src/Checkout.php',
        ],
        [
            'type' => ItemType::Diff->value,
            'file' => 'src/Checkout.php',
            'diff' => "--- Original\n+++ New\n@@\n-old\n+new",
            'applied_rectors' => [
                AddOverrideAttributeToOverriddenMethodsRector::class,
            ],
        ],
        [
            'type' => ItemType::Error->value,
            'file' => 'src/Checkout.php',
            'line' => 12,
            'message' => 'Could not parse file.',
            'caused_by' => 'Rector\\BrokenRector',
        ],
    ]);
});

it('uses changed file evidence when rector totals are inconsistent', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $report = (new RectorParser())->parse(json_encode([
        'totals' => [
            'changed_files' => 0,
            'errors' => 0,
        ],
        'changed_files' => [$source],
        'file_diffs' => [
            [
                'file' => $source,
                'diff' => "--- Original\n+++ New",
                'applied_rectors' => ['Rector\\ExampleRector'],
            ],
        ],
    ], JSON_THROW_ON_ERROR), '', $project->root());

    expect($report->changedFiles())->toBe(1);
    expect($report->findings())->toBe(1);
    expect($report->summary())->toMatchArray([
        'changed_files' => 1,
        'diffs' => 1,
    ]);
});
