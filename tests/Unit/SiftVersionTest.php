<?php

declare(strict_types=1);

use Sift\Sift;

it('exposes the current package version', function (): void {
    expect(Sift::VERSION)->toBe('2.4.0');
});
