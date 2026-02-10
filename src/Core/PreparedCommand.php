<?php

declare(strict_types=1);

namespace Sift\Core;

final readonly class PreparedCommand
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $command,
        public string $cwd,
        public array $env = [],
        public array $metadata = [],
    ) {}
}
