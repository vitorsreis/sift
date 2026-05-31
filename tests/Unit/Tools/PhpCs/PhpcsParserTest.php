<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\PhpCs\PhpcsParser;
use Tests\Support\FixtureProject;

it('normalizes phpcs totals and file messages', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $report = (new PhpcsParser())->parse(json_encode([
        'totals' => [
            'errors' => 1,
            'warnings' => 1,
            'fixable' => 1,
        ],
        'files' => [
            $source => [
                'errors' => 1,
                'warnings' => 1,
                'messages' => [
                    [
                        'message' => 'Line exceeds 120 characters.',
                        'source' => 'Generic.Files.LineLength.TooLong',
                        'severity' => 5,
                        'fixable' => false,
                        'type' => 'WARNING',
                        'line' => 9,
                        'column' => 121,
                    ],
                    [
                        'message' => 'Expected 1 space after comma.',
                        'source' => 'Squiz.Functions.FunctionDeclarationArgumentSpacing',
                        'severity' => 5,
                        'fixable' => true,
                        'type' => 'ERROR',
                        'line' => 12,
                        'column' => 27,
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR), '', $project->root());

    expect($report->findings())->toBe(2);
    expect($report->summary())->toBe([
        'errors' => 1,
        'warnings' => 1,
        'fixable' => 1,
        'files' => 1,
        'messages' => 2,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::Warning->value,
            'file' => 'src/Checkout.php',
            'line' => 9,
            'column' => 121,
            'message' => 'Line exceeds 120 characters.',
            'rule' => 'Generic.Files.LineLength.TooLong',
            'severity' => 5,
            'fixable' => false,
        ],
        [
            'type' => ItemType::Issue->value,
            'file' => 'src/Checkout.php',
            'line' => 12,
            'column' => 27,
            'message' => 'Expected 1 space after comma.',
            'rule' => 'Squiz.Functions.FunctionDeclarationArgumentSpacing',
            'severity' => 5,
            'fixable' => true,
        ],
    ]);
});
