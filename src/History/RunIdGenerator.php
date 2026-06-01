<?php

declare(strict_types=1);

namespace Sift\History;

final readonly class RunIdGenerator
{
    public function generate(): string
    {
        return 'run_' . bin2hex(random_bytes(16));
    }
}
