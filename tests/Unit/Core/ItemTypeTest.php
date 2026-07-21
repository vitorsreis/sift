<?php

declare(strict_types=1);

use Sift\Core\ItemType;

it('catalogs all public item types', function (): void {
    expect(array_column(ItemType::cases(), 'value'))->toBe([
        'test_failure',
        'test_error',
        'coverage',
        'issue',
        'warning',
        'error',
        'syntax_error',
        'diff',
        'changed_file',
        'dependency',
        'unused_dependency',
        'missing_dependency',
        'vulnerability',
        'license',
        'package',
        'architecture_violation',
        'mutation',
        'benchmark',
    ]);
});

it('keeps item type values unique', function (): void {
    $values = array_column(ItemType::cases(), 'value');

    expect(array_unique($values))->toHaveCount(count($values));
});
