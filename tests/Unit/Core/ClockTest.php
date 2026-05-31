<?php

declare(strict_types=1);

use Sift\Core\SystemClock;

it('provides utc wall time and monotonic time', function (): void {
    $clock = new SystemClock();

    expect($clock->now()->getTimezone()->getName())->toBe('UTC');
    expect($clock->monotonicSeconds())->toBeGreaterThan(0);
});
