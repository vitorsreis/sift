<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;

interface CommandHandler
{
    /**
     * @return array<string, mixed>
     */
    public function handle(CommandRoute $route, string $cwd): array;
}
