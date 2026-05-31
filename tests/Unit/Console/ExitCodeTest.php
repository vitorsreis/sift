<?php

declare(strict_types=1);

use Sift\Console\ExitCode;

it('catalogs process exit codes', function (): void {
    expect(ExitCode::Success->value)->toBe(0);
    expect(ExitCode::Findings->value)->toBe(1);
    expect(ExitCode::OperationalError->value)->toBe(2);
    expect(ExitCode::UserError->value)->toBe(3);
    expect(ExitCode::Interrupted->value)->toBe(130);
});
