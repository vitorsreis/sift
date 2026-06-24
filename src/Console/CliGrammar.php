<?php

declare(strict_types=1);

namespace Sift\Console;

final class CliGrammar
{
    /**
     * @return list<CliOption>
     */
    public static function globalOptions(): array
    {
        return [
            CliOption::boolean('compact'),
            CliOption::boolean('full'),
            CliOption::boolean('pretty', 'p'),
            CliOption::boolean('no-pretty', 'P'),
            CliOption::boolean('json'),
            CliOption::boolean('no-json'),
            CliOption::boolean('raw'),
            CliOption::boolean('show-process'),
            CliOption::boolean('no-show-process'),
            CliOption::boolean('debug'),
            CliOption::boolean('history'),
            CliOption::boolean('no-history'),
            CliOption::string('config', 'c'),
            CliOption::string('d', repeatable: true),
        ];
    }

    /**
     * @return array<string, list<CliOption>>
     */
    public static function commandOptions(): array
    {
        $configOptions = [CliOption::string('config', 'c')];
        $historyOptions = [
            CliOption::integer('limit', 'l'),
            CliOption::integer('offset', 'o'),
            CliOption::string('config', 'c'),
        ];
        $skillsScopedOptions = [
            CliOption::boolean('global', 'g'),
            CliOption::string('agent', 'a'),
            CliOption::string('skill', 's', repeatable: true),
        ];

        return [
            'init' => [
                CliOption::boolean('force', 'f'),
                CliOption::boolean('yes', 'y'),
                CliOption::boolean('skill'),
                CliOption::boolean('no-skill'),
                CliOption::string('config', 'c'),
            ],
            'validate' => $configOptions,
            'tools list' => $configOptions,
            'skills add' => [
                ...$skillsScopedOptions,
                CliOption::boolean('list', 'l'),
                CliOption::boolean('yes', 'y'),
                CliOption::boolean('all'),
                CliOption::string('config', 'c'),
            ],
            'skills list' => $skillsScopedOptions,
            'skills remove' => [
                ...$skillsScopedOptions,
                CliOption::boolean('yes', 'y'),
                CliOption::boolean('all'),
            ],
            'skills update' => [
                ...$skillsScopedOptions,
                CliOption::boolean('yes', 'y'),
                CliOption::boolean('all'),
            ],
            'skills find' => [CliOption::string('owner')],
            'skills init' => [CliOption::boolean('yes', 'y')],
            'history list' => $historyOptions,
            'history view' => $historyOptions,
            'history clear' => $historyOptions,
            'history remove' => $historyOptions,
        ];
    }
}
