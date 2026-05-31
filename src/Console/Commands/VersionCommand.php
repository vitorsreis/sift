<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Sift;

final class VersionCommand
{
    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'version' => Sift::VERSION,
            ],
            'items' => [],
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'version',
            ],
        ];
    }
}
