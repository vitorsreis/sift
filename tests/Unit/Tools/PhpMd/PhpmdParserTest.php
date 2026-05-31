<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\PhpMd\PhpmdParser;
use Tests\Support\FixtureProject;

it('normalizes phpmd json violations and priorities', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $report = (new PhpmdParser())->parse("Deprecated: legacy notice\n" . json_encode([
        'version' => '2.15.0',
        'package' => 'PHPMD',
        'files' => [
            [
                'file' => $source,
                'violations' => [
                    [
                        'beginLine' => 12,
                        'endLine' => 14,
                        'package' => 'App\\Checkout',
                        'class' => 'CheckoutService',
                        'method' => 'run',
                        'description' => 'Avoid using static access.',
                        'rule' => 'StaticAccess',
                        'ruleSet' => 'Clean Code Rules',
                        'externalInfoUrl' => 'https://phpmd.org/rules/cleancode.html#staticaccess',
                        'priority' => 1,
                    ],
                    [
                        'beginLine' => 20,
                        'endLine' => 20,
                        'package' => null,
                        'class' => null,
                        'method' => null,
                        'description' => 'Avoid unused local variables.',
                        'rule' => 'UnusedLocalVariable',
                        'ruleSet' => 'Unused Code Rules',
                        'externalInfoUrl' => 'https://phpmd.org/rules/unusedcode.html#unusedlocalvariable',
                        'priority' => 3,
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR), '', $project->root());

    expect($report->violations())->toBe(2);
    expect($report->summary())->toBe([
        'files' => 1,
        'violations' => 2,
        'rules' => 2,
        'highest_priority' => 1,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::Issue->value,
            'file' => 'src/Checkout.php',
            'line' => 12,
            'end_line' => 14,
            'message' => 'Avoid using static access.',
            'rule' => 'StaticAccess',
            'ruleset' => 'Clean Code Rules',
            'priority' => 1,
            'external_info_url' => 'https://phpmd.org/rules/cleancode.html#staticaccess',
            'package' => 'App\\Checkout',
            'class' => 'CheckoutService',
            'method' => 'run',
        ],
        [
            'type' => ItemType::Issue->value,
            'file' => 'src/Checkout.php',
            'line' => 20,
            'end_line' => 20,
            'message' => 'Avoid unused local variables.',
            'rule' => 'UnusedLocalVariable',
            'ruleset' => 'Unused Code Rules',
            'priority' => 3,
            'external_info_url' => 'https://phpmd.org/rules/unusedcode.html#unusedlocalvariable',
        ],
    ]);
});
