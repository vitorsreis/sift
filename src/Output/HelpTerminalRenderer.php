<?php

declare(strict_types=1);

namespace Sift\Output;

final class HelpTerminalRenderer
{
    public function render(?TerminalStyle $style = null): string
    {
        $style ??= new TerminalStyle();
        $lines = [
            ...$style->siftBanner(),
            '',
            $style->muted('Sift agent tooling and skills layer for PHP projects.'),
            '',
            $style->heading('Usage'),
            '  ' . $this->syntax([
                ['command', 'composer'],
                ['command', 'sift'],
                ['argument', '[options]'],
                ['argument', '<command>'],
                ['argument', '[args]'],
            ], $style),
            '  ' . $this->syntax([
                ['command', 'composer'],
                ['command', 'sift'],
                ['argument', '[options]'],
                ['argument', '<tool>'],
                ['argument', '[args]'],
            ], $style),
            '  ' . $this->syntax([
                ['command', 'composer'],
                ['command', 'skills'],
                ['argument', '[options]'],
                ['argument', '<command>'],
            ], $style),
            '',
            $style->heading('Commands'),
            $this->row('<tool> [args]', $this->syntax([
                ['argument', '<tool>'],
                ['argument', '[args]'],
            ], $style), 'Shortcut for run <tool>.'),
            $this->row('run <tool> [args]', $this->syntax([
                ['command', 'run'],
                ['argument', '<tool>'],
                ['argument', '[args]'],
            ], $style), 'Run a tool through Sift.'),
            $this->row('tools list', $this->syntax([
                ['command', 'tools'],
                ['command', 'list'],
            ], $style), 'List supported tools and local availability.'),
            '',
            $this->row('history list', $this->syntax([
                ['command', 'history'],
                ['command', 'list'],
            ], $style), 'List stored runs.'),
            $this->row('history view <run_id>', $this->syntax([
                ['command', 'history'],
                ['command', 'view'],
                ['argument', '<run_id>'],
            ], $style), 'Show a stored run.'),
            '',
            $this->row('skills list', $this->syntax([
                ['command', 'skills'],
                ['command', 'list'],
            ], $style), 'List installed skills.'),
            $this->row('skills add <source>', $this->syntax([
                ['command', 'skills'],
                ['command', 'add'],
                ['argument', '<source>'],
            ], $style), 'Install skills from a source.'),
            $this->row('skills find [query]', $this->syntax([
                ['command', 'skills'],
                ['command', 'find'],
                ['argument', '[query]'],
            ], $style), 'Search available skills.'),
            $this->row('skills init [name]', $this->syntax([
                ['command', 'skills'],
                ['command', 'init'],
                ['argument', '[name]'],
            ], $style), 'Scaffold a skill.'),
            $this->row('skills remove <skill>', $this->syntax([
                ['command', 'skills'],
                ['command', 'remove'],
                ['argument', '<skill>'],
            ], $style), 'Remove installed skills.'),
            $this->row('skills update [skill ...]', $this->syntax([
                ['command', 'skills'],
                ['command', 'update'],
                ['argument', '[skill ...]'],
            ], $style), 'Update installed skills.'),
            '',
            $this->row('init', $style->command('init'), 'Create a sift.json config.'),
            $this->row('validate', $style->command('validate'), 'Validate sift.json.'),
            $this->row('version', $style->command('version'), 'Show the installed Sift version.'),
            $this->row('help', $style->command('help'), 'Show this reference.'),
            '',
            $style->heading('Options'),
            $this->row('--json', $style->argument('--json'), 'Render normalized JSON for supported commands.'),
            $this->row('--no-json', $style->argument('--no-json'), 'Force terminal output.'),
            $this->row('--compact', $style->argument('--compact'), 'Keep result output short.'),
            $this->row('--full', $style->argument('--full'), 'Show complete result output.'),
            $this->row('--pretty, -p', $style->argument('--pretty') . ', ' . $style->argument('-p'), 'Pretty-print JSON output.'),
            $this->row('--raw', $style->argument('--raw'), 'Stream native tool output.'),
            $this->row('--show-process', $style->argument('--show-process'), 'Show prepared process on STDERR.'),
            $this->row('--no-color', $style->argument('--no-color'), 'Disable terminal color feedback.'),
            $this->row('--history / --no-history', $style->argument('--history') . ' / ' . $style->argument('--no-history'), 'Force or skip history for a run.'),
            $this->row('--config=<path>, -c <path>', $style->argument('--config=<path>') . ', ' . $style->argument('-c') . ' ' . $style->argument('<path>'), 'Use a specific config file.'),
            '',
            $style->heading('Terminal-only commands'),
            $this->row('help, version, tools list', $style->command('help') . ', ' . $style->command('version') . ', ' . $style->command('tools') . ' ' . $style->command('list'), 'Always render terminal output.'),
            '',
            $style->heading('Examples'),
            '  ' . $this->syntax([
                ['command', 'composer'],
                ['command', 'sift'],
                ['command', 'pest'],
            ], $style),
            '  ' . $this->syntax([
                ['command', 'composer'],
                ['command', 'sift'],
                ['argument', '--compact'],
                ['command', 'phpstan'],
                ['command', 'analyse'],
                ['plain', 'src'],
            ], $style),
            '  ' . $this->syntax([
                ['command', 'composer'],
                ['command', 'sift'],
                ['argument', '--json'],
                ['argument', '--compact'],
                ['command', 'pest'],
            ], $style),
            '  ' . $this->syntax([
                ['command', 'composer'],
                ['command', 'sift'],
                ['argument', '--no-json'],
                ['command', 'validate'],
            ], $style),
            '  ' . $this->syntax([
                ['command', 'composer'],
                ['command', 'sift'],
                ['argument', '--full'],
                ['command', 'history'],
                ['command', 'view'],
                ['argument', '<run_id>'],
            ], $style),
        ];

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param list<array{0: 'argument'|'command'|'plain', 1: string}> $parts
     */
    private function syntax(array $parts, TerminalStyle $style): string
    {
        return implode(' ', array_map(
            static fn(array $part): string => match ($part[0]) {
                'argument' => $style->argument($part[1]),
                'command' => $style->command($part[1]),
                'plain' => $part[1],
            },
            $parts,
        ));
    }

    private function row(string $raw, string $styled, string $description): string
    {
        return '  ' . $styled . str_repeat(' ', max(2, 30 - strlen($raw))) . $description;
    }
}
