<?php

declare(strict_types=1);

namespace Sift\Output;

final class SkillsFindTerminalRenderer
{
    /**
     * @param array<string, mixed> $payload
     */
    public function render(array $payload): string
    {
        $meta = $this->object($payload['meta'] ?? null);
        $mode = $this->string($meta['mode'] ?? null);

        if ($mode === 'agent_tip') {
            return $this->agentTip();
        }

        if ($mode === 'cancelled') {
            return 'Search cancelled';
        }

        $query = $this->string($meta['query'] ?? null);
        $owner = $this->string($meta['owner'] ?? null);
        $items = $this->list($payload['items'] ?? null);

        if ($items === []) {
            $suffix = $owner === '' ? '' : sprintf(' from owner "%s"', $owner);

            return sprintf('No skills found for "%s"%s', $query, $suffix);
        }

        $lines = [
            'Install with composer skills add <owner/repo@skill>',
            '',
        ];

        foreach (array_slice($items, 0, 6) as $item) {
            $entry = $this->entry($item);

            if ($entry === null) {
                continue;
            }

            $lines[] = $entry['headline'];
            $lines[] = "\u{2514} " . $entry['url'];
            $lines[] = '';
        }

        while (end($lines) === '') {
            array_pop($lines);
        }

        return implode(PHP_EOL, $lines);
    }

    private function agentTip(): string
    {
        return implode(PHP_EOL, [
            'Tip: if running in a coding agent, follow these steps:',
            '  1) composer skills find [query] [--owner <owner>]',
            '  2) composer skills add <owner/repo@skill>',
            '',
            'Usage: composer skills find <query> [--owner <owner>]',
        ]);
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return null|array{headline: string, url: string}
     */
    private function entry(array $item): ?array
    {
        $name = $this->string($item['name'] ?? null);
        $source = $this->string($item['source'] ?? null);

        if ($name === '' || $source === '') {
            return null;
        }

        $installs = $this->installs($item['installs'] ?? null);
        $headline = $source . '@' . $name . ($installs === '' ? '' : ' ' . $installs);
        $slug = $this->string($item['slug'] ?? null);

        return [
            'headline' => $headline,
            'url' => 'https://skills.sh/' . ($slug === '' ? $source . '/' . $name : $slug),
        ];
    }

    private function installs(mixed $value): string
    {
        if (! is_int($value) || $value <= 0) {
            return '';
        }

        if ($value >= 1000000) {
            return $this->compactNumber($value / 1000000) . 'M installs';
        }

        if ($value >= 1000) {
            return $this->compactNumber($value / 1000) . 'K installs';
        }

        return $value . ' install' . ($value === 1 ? '' : 's');
    }

    private function compactNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
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
     * @return list<array<string, mixed>>
     */
    private function list(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (array_is_list($item)) {
                continue;
            }

            $object = [];

            foreach ($item as $key => $field) {
                if (is_string($key)) {
                    $object[$key] = $field;
                }
            }

            $items[] = $object;
        }

        return $items;
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
