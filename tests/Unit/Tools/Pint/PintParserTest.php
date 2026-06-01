<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\Pint\PintParser;
use Tests\Support\FixtureProject;

it('normalizes pint noisy json files and fixers', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $json = json_encode([
        'files' => [
            [
                'path' => $source,
                'fixers' => [
                    'ordered_imports',
                    'braces_position',
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $report = (new PintParser())->parse("noise before\n{$json}\nnoise after", '', $project->root());

    expect($report->result())->toBe('fail');
    expect($report->files())->toBe(1);
    expect($report->fixers())->toBe(2);
    expect($report->summary())->toBe([
        'result' => 'fail',
        'files' => 1,
        'fixers' => 2,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::ChangedFile->value,
            'file' => 'src/Checkout.php',
            'fixers' => [
                'ordered_imports',
                'braces_position',
            ],
        ],
    ]);
});
