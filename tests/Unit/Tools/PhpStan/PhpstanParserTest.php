<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\PhpStan\PhpstanParser;
use Tests\Support\FixtureProject;

it('normalizes phpstan totals and file messages', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $report = (new PhpstanParser())->parse(json_encode([
        'totals' => [
            'errors' => 0,
            'file_errors' => 2,
        ],
        'files' => [
            $source => [
                'errors' => 2,
                'messages' => [
                    [
                        'message' => 'Call to an undefined method Checkout::pay().',
                        'line' => 12,
                        'ignorable' => true,
                        'identifier' => 'method.notFound',
                    ],
                    [
                        'message' => 'Property Checkout::$total has no type.',
                        'line' => 7,
                    ],
                ],
            ],
        ],
        'errors' => [],
    ], JSON_THROW_ON_ERROR), '', $project->root());

    expect($report->findings())->toBe(2);
    expect($report->summary())->toBe([
        'errors' => 0,
        'file_errors' => 2,
        'files' => 1,
        'messages' => 2,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::Issue->value,
            'file' => 'src/Checkout.php',
            'line' => 12,
            'message' => 'Call to an undefined method Checkout::pay().',
            'identifier' => 'method.notFound',
            'ignorable' => true,
        ],
        [
            'type' => ItemType::Issue->value,
            'file' => 'src/Checkout.php',
            'line' => 7,
            'message' => 'Property Checkout::$total has no type.',
        ],
    ]);
});

it('normalizes phpstan top-level errors', function (): void {
    $report = (new PhpstanParser())->parse(json_encode([
        'totals' => [
            'errors' => 1,
            'file_errors' => 0,
        ],
        'files' => [],
        'errors' => ['Internal PHPStan error.'],
    ], JSON_THROW_ON_ERROR), '', getcwd() ?: '.');

    expect($report->findings())->toBe(1);
    expect($report->summary())->toMatchArray(['errors' => 1, 'file_errors' => 0]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::Error->value,
            'message' => 'Internal PHPStan error.',
        ],
    ]);
});
