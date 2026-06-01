<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Skills\SkillScaffolder;

final readonly class SkillsInitCommand implements CommandHandler
{
    public function __construct(
        private SkillScaffolder $scaffolder = new SkillScaffolder(),
    ) {}

    public function handle(CommandRoute $route, string $cwd): array
    {
        $name = $route->arguments() === [] ? null : implode(' ', $route->arguments());
        $item = $this->scaffolder->scaffold($cwd, $name, ($route->options()['yes'] ?? false) === true);

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'created' => 1,
            ],
            'items' => [$item],
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'skills init',
            ],
        ];
    }
}
