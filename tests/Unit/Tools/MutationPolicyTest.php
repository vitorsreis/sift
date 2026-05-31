<?php

declare(strict_types=1);

use Sift\Tools\MutationPolicy;

it('catalogs mutation policies used by adapters', function (): void {
    expect(array_column(MutationPolicy::cases(), 'value'))->toBe([
        'never',
        'repair_flag',
        'explicit_dry_run',
        'tool_specific',
    ]);
});
