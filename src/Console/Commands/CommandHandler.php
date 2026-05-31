<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

interface CommandHandler
{
    /**
     * @return array<string, mixed>
     */
    public function handle(): array;
}
