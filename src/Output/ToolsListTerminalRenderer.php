<?php

declare(strict_types=1);

namespace Sift\Output;

final class ToolsListTerminalRenderer
{
    /**
     * @param array<string, mixed> $payload
     */
    public function render(array $payload): string
    {
        $items = $payload['items'] ?? [];
        $lines = [rtrim($this->header(), PHP_EOL)];

        if (! is_array($items) || ! array_is_list($items) || $items === []) {
            $lines[] = '  No tools found.';

            return implode(PHP_EOL, $lines);
        }

        foreach ($items as $item) {
            if (is_array($item) && ! array_is_list($item)) {
                $lines[] = rtrim($this->item($this->object($item)), PHP_EOL);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    public function header(): string
    {
        return 'Tools' . PHP_EOL
            . '  Supported tools and local availability.' . PHP_EOL
            . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $item
     */
    public function item(array $item): string
    {
        $installed = ($item['installed'] ?? null) === true && ($item['enabled'] ?? null) === true;
        $status = $installed ? $this->green('OK') : $this->red('NO');
        $name = $this->displayName($this->value($item['tool'] ?? 'tool'));
        $version = $item['version'] ?? null;

        if ($installed) {
            $suffix = $this->version($version);

            return trim($status . ' ' . $name . ($suffix === '' ? '' : ' ' . $suffix)) . PHP_EOL;
        }

        $hint = $item['install_hint'] ?? null;
        $install = is_string($hint) && $hint !== '' ? ', use `' . $hint . '`' : '';

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

    private function green(string $value): string
    {
        return "\033[32m" . $value . "\033[0m";
    }

    private function red(string $value): string
    {
        return "\033[31m" . $value . "\033[0m";
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
