<?php

declare(strict_types=1);

use Sift\Core\RunStatus;

it('catalogs normalized run statuses', function (): void {
    expect(array_column(RunStatus::cases(), 'value'))->toBe([
        'passed',
        'failed',
        'changed',
        'error',
    ]);
});
