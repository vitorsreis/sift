<?php

declare(strict_types=1);

use Sift\Execution\Platform;

it('detects windows platform family', function (): void {
    expect((new Platform('Windows'))->isWindows())->toBeTrue();
    expect((new Platform('Linux'))->isWindows())->toBeFalse();
});
