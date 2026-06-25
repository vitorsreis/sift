<?php

declare(strict_types=1);

namespace Sift\Output;

final readonly class TerminalStyle
{
    private const string RESET = "\033[0m";

    private const array BANNER_PALETTE = [250, 248, 246, 244, 242, 240];

    public function __construct(
        private bool $enabled = true,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function bold(string $value): string
    {
        return $this->sequence('1', $value);
    }

    public function muted(string $value): string
    {
        return $this->color($value, 245);
    }

    public function cyan(string $value): string
    {
        return $this->color($value, 45);
    }

    public function yellow(string $value): string
    {
        return $this->color($value, 228);
    }

    public function green(string $value): string
    {
        return $this->color($value, 82);
    }

    public function red(string $value): string
    {
        return $this->color($value, 203);
    }

    public function magenta(string $value): string
    {
        return $this->color($value, 175);
    }

    public function blue(string $value): string
    {
        return $this->color($value, 117);
    }

    public function label(string $value): string
    {
        return $this->muted($value);
    }

    public function heading(string $value): string
    {
        return $this->sequence('1;38;5;250', $value);
    }

    public function command(string $value): string
    {
        return $this->cyan($value);
    }

    public function argument(string $value): string
    {
        return $this->yellow($value);
    }

    public function success(string $value): string
    {
        return $this->green($value);
    }

    public function warning(string $value): string
    {
        return $this->yellow($value);
    }

    public function status(string $value): string
    {
        return match ($value) {
            'passed', 'ok', 'installed', 'updated', 'removed', 'created' => $this->green($value),
            'failed', 'error', 'missing' => $this->red($value),
            default => $this->yellow($value),
        };
    }

    public function errorBadge(): string
    {
        if (! $this->enabled) {
            return 'ERROR';
        }

        return "\033[41m\033[37m\033[1mERROR" . self::RESET;
    }

    /**
     * @return list<string>
     */
    public function siftBanner(): array
    {
        return $this->gradientLines([
            '███████╗██╗███████╗████████╗',
            '██╔════╝██║██╔════╝╚══██╔══╝',
            '███████╗██║█████╗     ██║',
            '╚════██║██║██╔══╝     ██║',
            '███████║██║██║        ██║',
            '╚══════╝╚═╝╚═╝        ╚═╝',
        ]);
    }

    /**
     * @return list<string>
     */
    public function siftSkillsBanner(): array
    {
        return $this->gradientLines([
            '███████╗██╗███████╗████████╗    ███████╗██╗  ██╗██╗██╗     ██╗     ███████╗',
            '██╔════╝██║██╔════╝╚══██╔══╝    ██╔════╝██║ ██╔╝██║██║     ██║     ██╔════╝',
            '███████╗██║█████╗     ██║       ███████╗█████╔╝ ██║██║     ██║     ███████╗',
            '╚════██║██║██╔══╝     ██║       ╚════██║██╔═██╗ ██║██║     ██║     ╚════██║',
            '███████║██║██║        ██║       ███████║██║  ██╗██║███████╗███████╗███████║',
            '╚══════╝╚═╝╚═╝        ╚═╝       ╚══════╝╚═╝  ╚═╝╚═╝╚══════╝╚══════╝╚══════╝',
        ]);
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public function gradientLines(array $lines): array
    {
        if (! $this->enabled) {
            return $lines;
        }

        $styled = [];
        $lastPaletteIndex = count(self::BANNER_PALETTE) - 1;

        foreach ($lines as $index => $line) {
            $paletteIndex = min($index, $lastPaletteIndex);
            $styled[] = $this->color($line, self::BANNER_PALETTE[$paletteIndex]);
        }

        return $styled;
    }

    private function color(string $value, int $color): string
    {
        return $this->sequence('38;5;' . $color, $value);
    }

    private function sequence(string $sequence, string $value): string
    {
        if (! $this->enabled || $value === '') {
            return $value;
        }

        return "\033[" . $sequence . 'm' . $value . self::RESET;
    }
}
