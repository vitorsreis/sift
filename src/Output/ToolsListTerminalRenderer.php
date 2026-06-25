<?php

declare(strict_types=1);

namespace Sift\Output;

final class ToolsListTerminalRenderer
{
    /**
     * @param array<string, mixed> $payload
     */
    public function render(array $payload, ?TerminalStyle $style = null): string
    {
        $style ??= new TerminalStyle();
        $items = $payload['items'] ?? [];
        $lines = [rtrim($this->header($style), PHP_EOL)];

        if (! is_array($items) || ! array_is_list($items) || $items === []) {
            $lines[] = '  ' . $style->muted('No tools found.');

            return implode(PHP_EOL, $lines);
        }

        foreach ($items as $item) {
            if (is_array($item) && ! array_is_list($item)) {
                $lines[] = rtrim($this->item($this->object($item), $style), PHP_EOL);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    public function header(?TerminalStyle $style = null): string
    {
        $style ??= new TerminalStyle();

        return $style->heading('Tools') . PHP_EOL
            . '  ' . $style->muted('Supported tools and local availability.') . PHP_EOL
            . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $item
     */
    public function item(array $item, ?TerminalStyle $style = null): string
    {
        $style ??= new TerminalStyle();
        $installed = ($item['installed'] ?? null) === true && ($item['enabled'] ?? null) === true;
        $status = $installed ? $style->success('OK') : $style->red('NO');
        $name = $style->command($this->displayName($this->value($item['tool'] ?? 'tool')));
        $version = $item['version'] ?? null;

        if ($installed) {
            $suffix = $this->version($version);
            $suffix = $suffix === '' ? '' : ' ' . $style->muted($suffix);

            return trim($status . ' ' . $name . $suffix) . PHP_EOL;
        }

        $hint = $item['install_hint'] ?? null;
        $install = is_string($hint) && $hint !== '' ? ', use `' . $style->command($hint) . '`' : '';

        return $status . ' ' . $name . $install . PHP_EOL;
    }

    private function version(mixed $version): string
    {
        if (! is_string($version) || $version === '') {
            return '';
        }

        $clean = preg_replace('/\e\[[\d;]*m/', '', $version);
        $clean = is_string($clean) ? trim($clean) : '';

        if ($clean === '') {
            return '';
        }

        if (preg_match('/\bv?\d+(?:\.\d+)+(?:[-+@][^\s]+)?\b/i', $clean, $matches) === 1) {
            return $matches[0];
        }

        $lines = preg_split('/\R/', $clean);

        return is_array($lines) ? trim($lines[0]) : $clean;
    }

    private function displayName(string $tool): string
    {
        return match ($tool) {
            'phpunit' => 'PHPUnit',
            'phpstan' => 'PHPStan',
            'phpcs' => 'PHPCS',
            'phpmd' => 'PHPMD',
            'php-cs-fixer' => 'PHP-CS-Fixer',
            default => str_replace(' ', '-', ucwords(str_replace('-', ' ', $tool))),
        };
    }

    private function value(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : 'tool';
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<string, mixed>
     */
    private function object(array $value): array
    {
        $object = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $object[$key] = $item;
            }
        }

        return $object;
    }
}
