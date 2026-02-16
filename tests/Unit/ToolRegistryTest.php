<?php

declare(strict_types=1);

use Sift\Registry\ToolRegistry;
use Tests\Support\FakeToolAdapter;

it('lists names and resolves tools by name', function (): void {
    $alpha = new FakeToolAdapter('alpha', 'Install alpha.', ['vendor/bin/alpha']);
    $beta = new FakeToolAdapter('beta', 'Install beta.', ['vendor/bin/beta']);
    $registry = new ToolRegistry([$alpha, $beta]);

    expect($registry->all())->toBe([$alpha, $beta])
        ->and($registry->names())->toBe(['alpha', 'beta'])
        ->and($registry->find('beta'))->toBe($beta)
        ->and($registry->find('missing'))->toBeNull();
});
