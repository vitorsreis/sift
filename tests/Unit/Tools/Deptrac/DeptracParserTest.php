<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Tools\Deptrac\DeptracParser;

it('normalizes deptrac json violations and skipped violations', function (): void {
    $report = (new DeptracParser())->parse(json_encode(deptracJsonReport(), JSON_THROW_ON_ERROR), '', getcwd() ?: '.');

    expect($report->summary())->toBe([
        'violations' => 1,
        'skipped_violations' => 1,
        'uncovered' => 1,
        'allowed' => 4,
        'warnings' => 0,
        'errors' => 0,
        'files' => 1,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::ArchitectureViolation->value,
            'file' => 'src/Application/Checkout.php',
            'line' => 12,
            'message' => 'App\\Controller\\CheckoutController must not depend on App\\Infrastructure\\PaymentGateway (Controller on Infrastructure)',
            'rule' => 'forbidden_dependency',
            'layer' => 'Controller',
            'depender' => 'App\\Controller\\CheckoutController',
            'dependency' => 'App\\Infrastructure\\PaymentGateway',
            'depender_layer' => 'Controller',
            'dependent_layer' => 'Infrastructure',
        ],
        [
            'type' => ItemType::Warning->value,
            'file' => 'src/Application/Checkout.php',
            'line' => 18,
            'message' => 'App\\Domain\\Order should not depend on App\\Infrastructure\\Logger (Domain on Infrastructure)',
            'rule' => 'skipped_violation',
            'layer' => 'Domain',
            'depender' => 'App\\Domain\\Order',
            'dependency' => 'App\\Infrastructure\\Logger',
            'depender_layer' => 'Domain',
            'dependent_layer' => 'Infrastructure',
        ],
        [
            'type' => ItemType::Warning->value,
            'file' => 'src/Application/Checkout.php',
            'line' => 24,
            'message' => 'App\\Service\\Checkout has uncovered dependency on App\\Domain\\Order (Application)',
            'rule' => 'uncovered_dependency',
            'layer' => 'Application',
            'depender' => 'App\\Service\\Checkout',
            'dependency' => 'App\\Domain\\Order',
        ],
    ]);
});

/**
 * @return array<string, mixed>
 */
function deptracJsonReport(): array
{
    return [
        'Report' => [
            'Violations' => 1,
            'Skipped violations' => 1,
            'Uncovered' => 1,
            'Allowed' => 4,
            'Warnings' => 0,
            'Errors' => 0,
        ],
        'files' => [
            'src/Application/Checkout.php' => [
                'messages' => [
                    [
                        'message' => 'App\\Controller\\CheckoutController must not depend on App\\Infrastructure\\PaymentGateway (Controller on Infrastructure)',
                        'line' => 12,
                        'type' => 'error',
                    ],
                    [
                        'message' => 'App\\Domain\\Order should not depend on App\\Infrastructure\\Logger (Domain on Infrastructure)',
                        'line' => 18,
                        'type' => 'warning',
                    ],
                    [
                        'message' => 'App\\Service\\Checkout has uncovered dependency on App\\Domain\\Order (Application)',
                        'line' => 24,
                        'type' => 'warning',
                    ],
                ],
                'violations' => 3,
            ],
        ],
    ];
}
