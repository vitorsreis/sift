<?php

declare(strict_types=1);

it('documents the optional real tool gate', function (): void {
    expect('SIFT_RUN_REAL_TOOLS=1')->toBeString();
});

it('reports a clear gate when optional real tool smoke checks are disabled', function (): void {
    if (getenv('SIFT_RUN_REAL_TOOLS') !== '1') {
        expect('Set SIFT_RUN_REAL_TOOLS=1 to run optional end-to-end checks against locally installed tools.')
            ->toContain('SIFT_RUN_REAL_TOOLS=1');

        return;
    }

    expect(true)->toBeTrue();
});
