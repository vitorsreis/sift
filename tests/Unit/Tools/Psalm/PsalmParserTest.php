<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\Psalm\PsalmParser;
use Tests\Support\FixtureProject;

it('normalizes psalm json issues', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $report = (new PsalmParser())->parse(json_encode([
        [
            'severity' => 'error',
            'type' => 'UndefinedVariable',
            'message' => 'Cannot find referenced variable $total',
            'file_path' => $source,
            'line_from' => 12,
            'column_from' => 9,
        ],
        [
            'severity' => 'info',
            'type' => 'PossiblyNullArgument',
            'message' => 'Argument may be null',
            'file_name' => $source,
            'line_from' => 18,
        ],
    ], JSON_THROW_ON_ERROR), '', $project->root());

    expect($report->findings())->toBe(2);
    expect($report->summary())->toBe([
        'issues' => 2,
        'errors' => 1,
        'warnings' => 0,
        'info' => 1,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::Issue->value,
            'severity' => 'error',
            'issue_type' => 'UndefinedVariable',
            'file' => 'src/Checkout.php',
            'line' => 12,
            'column' => 9,
            'message' => 'Cannot find referenced variable $total',
        ],
        [
            'type' => ItemType::Issue->value,
            'severity' => 'info',
            'issue_type' => 'PossiblyNullArgument',
            'file' => 'src/Checkout.php',
            'line' => 18,
            'message' => 'Argument may be null',
        ],
    ]);
});
