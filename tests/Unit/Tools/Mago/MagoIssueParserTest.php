<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\Mago\MagoIssueParser;
use Tests\Support\FixtureProject;

it('normalizes mago issue json output', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $report = (new MagoIssueParser())->parse(json_encode([
        'issues' => [
            [
                'level' => 'warning',
                'code' => 'lint:no-empty',
                'message' => 'Empty block detected.',
                'notes' => ['Remove the empty block.'],
                'help' => 'Prefer explicit behavior.',
                'link' => 'https://mago.carthage.software/rules/no-empty',
                'annotations' => [
                    [
                        'message' => 'Empty block is here.',
                        'kind' => 'primary',
                        'span' => [
                            'file_id' => [
                                'name' => $source,
                                'path' => $source,
                                'size' => 42,
                                'file_type' => 'host',
                            ],
                            'start' => [
                                'offset' => 10,
                                'line' => 7,
                                'column' => 5,
                            ],
                            'end' => [
                                'offset' => 12,
                                'line' => 7,
                            ],
                        ],
                    ],
                ],
                'edits' => [
                    [
                        [
                            'name' => $source,
                            'path' => $source,
                            'size' => 42,
                            'file_type' => 'host',
                        ],
                        [],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR), '', $project->root());

    expect($report->findings())->toBe(1);
    expect($report->summary())->toMatchArray([
        'issues' => 1,
        'warnings' => 1,
        'fixable' => 1,
        'files' => 1,
    ]);
    expect($report->items())->toHaveCount(1);
    expect($report->items()[0])->toMatchArray([
        'type' => ItemType::Warning->value,
        'file' => 'src/Checkout.php',
        'line' => 7,
        'column' => 5,
        'message' => 'Empty block detected.',
        'rule' => 'lint:no-empty',
        'severity' => 'warning',
        'issue_type' => 'primary',
        'annotation' => 'Empty block is here.',
        'notes' => ['Remove the empty block.'],
        'help' => 'Prefer explicit behavior.',
        'link' => 'https://mago.carthage.software/rules/no-empty',
        'fixable' => true,
    ]);
});
