<?php

declare(strict_types=1);

namespace Sift\Output;

final class SkillsTerminalRenderer
{
    /**
     * @param array<string, mixed> $payload
     */
    public function render(array $payload, ?TerminalStyle $style = null): string
    {
        $style ??= new TerminalStyle();
        $subcommand = $this->string($this->object($payload['meta'] ?? null)['subcommand'] ?? null);

        return match ($subcommand) {
            'skills' => $this->help($style),
            'skills list' => $this->list($payload, $style),
            'skills add --list' => $this->preview($payload, $style),
            'skills add' => $this->result($payload, 'Installed Skills', $style),
            'skills remove' => $this->result($payload, 'Removed Skills', $style),
            'skills update' => $this->result($payload, 'Updated Skills', $style),
            'skills use' => $this->use($payload),
            'skills init' => $this->init($payload, $style),
            default => $this->fallback($payload, $style),
        };
    }

    private function help(TerminalStyle $style): string
    {
        $lines = [
            ...$style->siftSkillsBanner(),
            '',
            $style->heading('Usage:') . ' ' . $this->syntax([
                ['command', 'composer'],
                ['command', 'skills'],
                ['argument', '<command>'],
                ['argument', '[options]'],
            ], $style),
            '',
            $style->heading('Manage Skills:'),
            $this->helpRow('add, a <package>', $this->syntax([
                ['command', 'add'],
                ['argument', '<package>'],
            ], $style), 'Add a skill package.'),
            $this->helpRow('', '', 'e.g. owner/repo'),
            $this->helpRow('', '', '     https://github.com/owner/repo'),
            $this->helpRow('add <package>@<skill>', $style->command('add') . ' ' . $style->argument('<package>@<skill>'), 'Add one skill from a package.'),
            $this->helpRow('find [query]', $this->syntax([
                ['command', 'find'],
                ['argument', '[query]'],
            ], $style), 'Search for skills interactively.'),
            $this->helpRow('use <package>@<skill>', $style->command('use') . ' ' . $style->argument('<package>@<skill>'), 'Generate a prompt without installing.'),
            $this->helpRow('list, ls', $style->command('list') . ', ' . $style->command('ls'), 'List installed skills.'),
            $this->helpRow('remove [skills...]', $this->syntax([
                ['command', 'remove'],
                ['argument', '[skills...]'],
            ], $style), 'Remove installed skills.'),
            '',
            $style->heading('Updates:'),
            $this->helpRow('update, upgrade [skills...]', $this->syntax([
                ['command', 'update'],
                ['argument', '[skills...]'],
            ], $style), 'Update skills to latest versions.'),
            '',
            $style->heading('Project:'),
            $this->helpRow('init [name]', $this->syntax([
                ['command', 'init'],
                ['argument', '[name]'],
            ], $style), 'Initialize a skill.'),
            '',
            $style->heading('Add Options:'),
            $this->helpRow('-g, --global', $style->argument('-g') . ', ' . $style->argument('--global'), 'Use user-level skill directories.'),
            $this->helpRow('-a, --agent <agents>', $style->argument('-a') . ', ' . $style->argument('--agent') . ' ' . $style->argument('<agents>'), 'Specify agents to install to.'),
            $this->helpRow('', '', 'Omit to choose agents and scope interactively.'),
            $this->helpRow('-s, --skill <skills>', $style->argument('-s') . ', ' . $style->argument('--skill') . ' ' . $style->argument('<skills>'), 'Specify skill names to install.'),
            $this->helpRow('-l, --list', $style->argument('-l') . ', ' . $style->argument('--list'), 'List available skills without installing.'),
            $this->helpRow('-y, --yes', $style->argument('-y') . ', ' . $style->argument('--yes'), 'Skip confirmation prompts.'),
            $this->helpRow('--copy', $style->argument('--copy'), 'Accepted for compatibility; Sift copies skill files.'),
            $this->helpRow('--subagent <names>', $style->argument('--subagent') . ' ' . $style->argument('<names>'), 'Install to Eve subagents; use root for agent/skills.'),
            $this->helpRow('--all', $style->argument('--all'), 'Install every skill into every supported agent.'),
            $this->helpRow('--full-depth', $style->argument('--full-depth'), 'Accepted for compatibility.'),
            $this->helpRow('--dangerously-accept-openclaw-risks', $style->argument('--dangerously-accept-openclaw-risks'), 'Accepted for compatibility.'),
            '',
            $style->heading('Use Options:'),
            $this->helpRow('-s, --skill <skill>', $style->argument('-s') . ', ' . $style->argument('--skill') . ' ' . $style->argument('<skill>'), 'Specify the skill to use.'),
            $this->helpRow('--full-depth', $style->argument('--full-depth'), 'Accepted for compatibility.'),
            $this->helpRow('--dangerously-accept-openclaw-risks', $style->argument('--dangerously-accept-openclaw-risks'), 'Accepted for compatibility.'),
            '',
            $style->heading('Update Options:'),
            $this->helpRow('-g, --global', $style->argument('-g') . ', ' . $style->argument('--global'), 'Use user-level skill directories.'),
            $this->helpRow('-p, --project', $style->argument('-p') . ', ' . $style->argument('--project'), 'Use project skill directories.'),
            $this->helpRow('-a, --agent <agents>', $style->argument('-a') . ', ' . $style->argument('--agent') . ' ' . $style->argument('<agents>'), 'Update specific agents.'),
            $this->helpRow('-s, --skill <skills>', $style->argument('-s') . ', ' . $style->argument('--skill') . ' ' . $style->argument('<skills>'), 'Update specific skills.'),
            $this->helpRow('-y, --yes', $style->argument('-y') . ', ' . $style->argument('--yes'), 'Skip confirmation prompts.'),
            $this->helpRow('--all', $style->argument('--all'), 'Update every managed skill in every supported agent.'),
            '',
            $style->heading('Remove Options:'),
            $this->helpRow('-g, --global', $style->argument('-g') . ', ' . $style->argument('--global'), 'Use user-level skill directories.'),
            $this->helpRow('-a, --agent <agents>', $style->argument('-a') . ', ' . $style->argument('--agent') . ' ' . $style->argument('<agents>'), 'Remove from specific agents.'),
            $this->helpRow('-s, --skill <skills>', $style->argument('-s') . ', ' . $style->argument('--skill') . ' ' . $style->argument('<skills>'), 'Specify skills to remove.'),
            $this->helpRow('-y, --yes', $style->argument('-y') . ', ' . $style->argument('--yes'), 'Skip confirmation prompts.'),
            $this->helpRow('--all', $style->argument('--all'), 'Remove every managed skill from every supported agent.'),
            '',
            $style->heading('List Options:'),
            $this->helpRow('-g, --global', $style->argument('-g') . ', ' . $style->argument('--global'), 'List user-level skill directories.'),
            $this->helpRow('-a, --agent <agents>', $style->argument('-a') . ', ' . $style->argument('--agent') . ' ' . $style->argument('<agents>'), 'Filter by specific agents.'),
            $this->helpRow('-s, --skill <skills>', $style->argument('-s') . ', ' . $style->argument('--skill') . ' ' . $style->argument('<skills>'), 'Filter by specific skills.'),
            $this->helpRow('--json', $style->argument('--json'), 'Output normalized JSON.'),
            '',
            $style->heading('Terminal Options:'),
            $this->helpRow('--no-color', $style->argument('--no-color'), 'Disable terminal color feedback.'),
            '',
            $style->heading('Examples:'),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'find']], $style),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'find'], ['plain', 'php']], $style),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'add'], ['plain', 'owner/repo@skill']], $style),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'add'], ['plain', 'owner/repo'], ['argument', '--skill'], ['plain', 'review'], ['argument', '--agent=standard'], ['argument', '--yes']], $style),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'add'], ['plain', 'owner/repo'], ['argument', '--skill'], ['plain', 'pr-review'], ['plain', 'commit'], ['argument', '--agent'], ['plain', 'standard'], ['plain', 'cursor'], ['argument', '--yes']], $style),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'add'], ['plain', 'owner/repo'], ['argument', '--skill'], ['plain', 'review'], ['argument', '--agent=standard'], ['argument', '--global'], ['argument', '--yes']], $style),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'use'], ['plain', 'owner/repo@skill']], $style),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'add'], ['plain', 'owner/repo'], ['argument', '--list']], $style),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'remove']], $style),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'remove'], ['plain', 'review'], ['argument', '--agent=standard'], ['argument', '--yes']], $style),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'list']], $style),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'update']], $style),
            '  ' . $this->syntax([['command', 'composer'], ['command', 'skills'], ['command', 'update'], ['plain', 'review'], ['argument', '--agent=standard'], ['argument', '--yes']], $style),
            '',
            'Discover more skills at ' . $style->blue('https://skills.sh/'),
        ];

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function list(array $payload, TerminalStyle $style): string
    {
        $items = $this->items($payload);
        $meta = $this->object($payload['meta'] ?? null);
        $global = ($meta['global'] ?? false) === true;
        $lines = [$style->heading($global ? 'Global Skills' : 'Project Skills'), ''];

        if ($items === []) {
            $lines[] = $style->muted($global ? 'No global skills installed.' : 'No project skills installed.');

            return implode(PHP_EOL, $lines);
        }

        foreach ($items as $item) {
            $name = $this->string($item['name'] ?? null);
            $source = $this->string($item['source'] ?? null);
            $targets = $this->stringList($item['targets'] ?? null);
            $parts = array_filter([
                $name === '' ? '' : $style->command($name),
                $source === '' ? '' : $style->muted($source),
                $targets === [] ? '' : $style->label('Agents:') . ' ' . implode(', ', $targets),
            ], static fn(string $part): bool => $part !== '');

            if ($parts !== []) {
                $lines[] = implode(' ', $parts);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function preview(array $payload, TerminalStyle $style): string
    {
        $items = $this->items($payload);
        $meta = $this->object($payload['meta'] ?? null);
        $source = $this->string($meta['source'] ?? null);
        $lines = [$style->heading('Available Skills'), ''];

        if ($items === []) {
            $lines[] = $style->muted('No skills found in this source.');

            return implode(PHP_EOL, $lines);
        }

        foreach ($items as $item) {
            $name = $this->string($item['name'] ?? null);
            $description = $this->string($item['description'] ?? null);

            if ($name === '') {
                continue;
            }

            $lines[] = $style->command($name);

            if ($description !== '') {
                $lines[] = $style->muted($description);
            }

            if ($source !== '') {
                $lines[] = sprintf(
                    'Install with %s %s %s %s',
                    $style->command('composer skills add'),
                    $style->argument($source),
                    $style->argument('--skill'),
                    $style->argument($name),
                );
            }

            $lines[] = '';
        }

        while (end($lines) === '') {
            array_pop($lines);
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function result(array $payload, string $title, TerminalStyle $style): string
    {
        $items = $this->items($payload);
        $lines = [$style->success($title), ''];

        if ($items === []) {
            $lines[] = $style->muted('No skills changed.');

            return implode(PHP_EOL, $lines);
        }

        foreach ($items as $item) {
            $name = $this->string($item['name'] ?? null);
            $target = $this->string($item['target'] ?? null);
            $action = $this->string($item['action'] ?? null);
            $path = $this->string($item['path'] ?? null);

            if ($name === '') {
                $parts = array_filter([$target, $action, $path], static fn(string $part): bool => $part !== '');

                if ($parts !== []) {
                    $lines[] = $style->muted(implode(' ', $parts));
                }

                continue;
            }

            $lines[] = $style->command($name);

            if ($target !== '' || $action !== '') {
                $targetLabel = $target === '' ? '' : $style->label($target . ':') . ' ';
                $actionLabel = $style->status($action === '' ? 'changed' : $action);
                $lines[] = '  ' . trim($targetLabel . $actionLabel);
            }

            if ($path !== '') {
                $lines[] = '  ' . $style->muted($path);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function use(array $payload): string
    {
        $extra = $this->object($payload['extra'] ?? null);
        $prompt = $this->string($extra['prompt'] ?? null);

        return $prompt === '' ? $this->fallback($payload, new TerminalStyle()) : $prompt;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function init(array $payload, TerminalStyle $style): string
    {
        $item = $this->items($payload)[0] ?? [];
        $name = $this->string($item['name'] ?? null);
        $path = $this->displaySkillPath($name, $this->string($item['path'] ?? null));

        if ($name === '') {
            return $this->result($payload, 'Created Skill', $style);
        }

        return implode(PHP_EOL, [
            $style->success('Initialized skill:') . ' ' . $style->command($name),
            '',
            $style->label('Created:'),
            '  ' . $style->muted($path === '' ? 'SKILL.md' : $path),
            '',
            $style->label('Next steps:'),
            '  1. Edit ' . $style->muted($path === '' ? 'SKILL.md' : $path) . ' to define your skill instructions',
            '  2. Update the name and description in the frontmatter',
            '',
            $style->label('Publishing:'),
            '  ' . $style->label('GitHub:') . ' Push to a repo, then ' . $style->command('composer skills add') . ' ' . $style->argument('<owner>/<repo>'),
            '  ' . $style->label('URL:') . '    Host the file, then ' . $style->command('composer skills add') . ' ' . $style->blue('https://example.com/' . $name . '/SKILL.md'),
            '',
            'Browse existing skills for inspiration at ' . $style->blue('https://skills.sh/'),
        ]);
    }

    private function displaySkillPath(string $name, string $path): string
    {
        if ($path === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $path);

        if ($name !== '' && str_ends_with($normalized, '/' . $name . '/SKILL.md')) {
            return $name . '/SKILL.md';
        }

        if (str_ends_with($normalized, '/SKILL.md') || $normalized === 'SKILL.md') {
            return 'SKILL.md';
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function fallback(array $payload, TerminalStyle $style): string
    {
        $summary = $this->object($payload['summary'] ?? null);
        $status = $this->string($payload['status'] ?? null) ?: 'passed';

        return trim($style->command('skills') . ' ' . $style->status($status) . ' ' . $this->fields($summary, $style));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    private function items(array $payload): array
    {
        $items = $payload['items'] ?? null;

        if (! is_array($items) || ! array_is_list($items)) {
            return [];
        }

        $objects = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (array_is_list($item)) {
                continue;
            }

            $object = [];

            foreach ($item as $key => $value) {
                if (is_string($key)) {
                    $object[$key] = $value;
                }
            }

            $objects[] = $object;
        }

        return $objects;
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value)) {
            return [];
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $object[$key] = $item;
            }
        }

        return $object;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            $item = $this->string($item);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function fields(array $fields, TerminalStyle $style): string
    {
        $parts = [];

        foreach ($fields as $key => $value) {
            $value = $this->string($value);

            if ($value !== '') {
                $parts[] = $style->label($key) . '=' . $value;
            }
        }

        return implode(' ', $parts);
    }

    private function helpRow(string $raw, string $styled, string $description): string
    {
        return '  ' . $styled . str_repeat(' ', max(2, 23 - strlen($raw))) . $description;
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

    private function string(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }
}
