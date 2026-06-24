<?php

declare(strict_types=1);

namespace Sift\Console;

use Closure;
use Sift\Skills\SkillCatalog;

final class InteractivePrompt
{
    private const string HIDE_CURSOR = "\033[?25l";

    private const string SHOW_CURSOR = "\033[?25h";

    private const string CLEAR_DOWN = "\033[J";

    /** @var null|resource */
    private mixed $windowsProcess = null;

    /** @var array<int, resource>|null */
    private ?array $windowsPipes = null;

    private ?string $sttyMode = null;

    /**
     * @param null|Closure(): string $keyReader
     * @param null|Closure(string): void $writer
     */
    public function __construct(
        private readonly ?Closure $keyReader = null,
        private readonly ?Closure $writer = null,
    ) {}

    /**
     * @return null|array<string, mixed>
     */
    public function searchSkills(SkillCatalog $catalog, ?string $owner = null): ?array
    {
        return $this->withTerminal(function () use ($catalog, $owner): ?array {
            $query = '';
            $results = [];
            $selected = 0;
            $lastRenderedLines = 0;
            $lastSearchedQuery = null;

            $render = function () use (&$lastRenderedLines, &$query, &$results, &$selected): void {
                if ($lastRenderedLines > 0) {
                    $this->write("\033[" . $lastRenderedLines . "A\033[1G");
                }

                $lines = $this->searchLines($query, $results, $selected);
                $this->write(self::CLEAR_DOWN . implode(PHP_EOL, $lines) . PHP_EOL);
                $lastRenderedLines = count($lines);
            };

            $render();

            while (true) {
                $key = $this->readKey();

                if ($key === 'escape' || $key === 'ctrl-c') {
                    return null;
                }

                if ($key === 'enter') {
                    return $results[$selected] ?? null;
                }

                if ($key === 'up') {
                    $selected = max(0, $selected - 1);
                    $render();

                    continue;
                }

                if ($key === 'down') {
                    $selected = min(max(0, count($results) - 1), $selected + 1);
                    $render();

                    continue;
                }

                if ($key === 'backspace') {
                    $query = substr($query, 0, -1);
                } elseif (str_starts_with($key, 'char:')) {
                    $query .= substr($key, 5);
                } else {
                    continue;
                }

                if (strlen($query) < 2) {
                    $results = [];
                    $selected = 0;
                    $lastSearchedQuery = null;
                    $render();

                    continue;
                }

                if ($query !== $lastSearchedQuery) {
                    $lastSearchedQuery = $query;
                    $results = $catalog->search($query, $owner);
                    $selected = 0;
                }

                $render();
            }
        });
    }

    /**
     * @param list<array{value: string, label: string, hint?: string, selected?: bool}> $options
     *
     * @return list<string>|null
     */
    public function multiselect(string $message, array $options): ?array
    {
        return $this->withTerminal(function () use ($message, $options): ?array {
            $cursor = 0;
            $selected = [];
            $lastRenderedLines = 0;

            foreach ($options as $option) {
                if (($option['selected'] ?? false) === true) {
                    $selected[$option['value']] = true;
                }
            }

            $render = function () use (&$lastRenderedLines, $message, $options, &$cursor, &$selected): void {
                if ($lastRenderedLines > 0) {
                    $this->write("\033[" . $lastRenderedLines . "A\033[1G");
                }

                $lines = [$message];

                foreach ($options as $index => $option) {
                    $marker = isset($selected[$option['value']]) ? '[x]' : '[ ]';
                    $pointer = $index === $cursor ? '>' : ' ';
                    $hint = isset($option['hint']) && $option['hint'] !== '' ? ' - ' . $option['hint'] : '';
                    $lines[] = sprintf('  %s %s %s%s', $pointer, $marker, $option['label'], $hint);
                }

                $lines[] = '';
                $lines[] = 'up/down navigate | space toggle | enter continue | esc cancel';

                $this->write(self::CLEAR_DOWN . implode(PHP_EOL, $lines) . PHP_EOL);
                $lastRenderedLines = count($lines);
            };

            $render();

            while (true) {
                $key = $this->readKey();

                if ($key === 'escape' || $key === 'ctrl-c') {
                    return null;
                }

                if ($key === 'up') {
                    $cursor = max(0, $cursor - 1);
                    $render();

                    continue;
                }

                if ($key === 'down') {
                    $cursor = min(count($options) - 1, $cursor + 1);
                    $render();

                    continue;
                }

                if ($key === 'space') {
                    $value = $options[$cursor]['value'];
                    if (isset($selected[$value])) {
                        unset($selected[$value]);
                    } else {
                        $selected[$value] = true;
                    }

                    $render();

                    continue;
                }

                if ($key === 'enter' && $selected !== []) {
                    return array_keys($selected);
                }
            }
        });
    }

    /**
     * @param list<array{value: string, label: string, hint?: string}> $options
     */
    public function select(string $message, array $options): ?string
    {
        $multiOptions = array_map(
            static fn(array $option): array => [
                'value' => $option['value'],
                'label' => $option['label'],
                'hint' => $option['hint'] ?? '',
                'selected' => false,
            ],
            $options,
        );
        $selected = $this->multiselect($message, $multiOptions);

        return $selected[0] ?? null;
    }

    public function confirm(string $message): bool
    {
        return $this->withTerminal(function () use ($message): bool {
            $this->write($message . ' [y/N] ');

            while (true) {
                $key = $this->readKey();

                if (in_array($key, ['escape', 'ctrl-c', 'enter'], true)) {
                    $this->write(PHP_EOL);

                    return false;
                }

                if ($key === 'char:y' || $key === 'char:Y') {
                    $this->write('y' . PHP_EOL);

                    return true;
                }

                if ($key === 'char:n' || $key === 'char:N') {
                    $this->write('n' . PHP_EOL);

                    return false;
                }
            }
        });
    }

    /**
     * @param list<array<string, mixed>> $results
     *
     * @return list<string>
     */
    private function searchLines(string $query, array $results, int $selected): array
    {
        $lines = [
            'Search skills: ' . $query . '_',
            '',
        ];

        if (strlen($query) < 2) {
            $lines[] = 'Start typing to search (min 2 chars)';
        } elseif ($results === []) {
            $lines[] = 'No skills found';
        } else {
            foreach (array_slice($results, 0, 8) as $index => $skill) {
                $name = is_string($skill['name'] ?? null) ? $skill['name'] : '';
                $source = is_string($skill['source'] ?? null) ? $skill['source'] : '';
                $installs = $this->formatInstalls($skill['installs'] ?? null);
                $pointer = $index === $selected ? '>' : ' ';
                $lines[] = sprintf('  %s %s %s%s', $pointer, $name, $source, $installs === '' ? '' : ' ' . $installs);
            }
        }

        $lines[] = '';
        $lines[] = 'up/down navigate | enter install | esc cancel';

        return $lines;
    }

    private function formatInstalls(mixed $value): string
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
     * @template T
     *
     * @param Closure(): T $callback
     *
     * @return T
     */
    private function withTerminal(Closure $callback): mixed
    {
        $this->enableRawMode();
        $this->write(self::HIDE_CURSOR);

        try {
            return $callback();
        } finally {
            $this->write(self::SHOW_CURSOR);
            $this->restoreTerminal();
        }
    }

    private function readKey(): string
    {
        if ($this->keyReader instanceof Closure) {
            return ($this->keyReader)();
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->readWindowsKey();
        }

        return $this->readUnixKey();
    }

    private function readUnixKey(): string
    {
        $char = fgetc(STDIN);

        if ($char === false) {
            return 'escape';
        }

        if ($char === "\033") {
            $second = fgetc(STDIN);
            $third = $second === '[' ? fgetc(STDIN) : false;

            return match ($third) {
                'A' => 'up',
                'B' => 'down',
                default => 'escape',
            };
        }

        return $this->keyFromCode(ord($char), ord($char));
    }

    private function readWindowsKey(): string
    {
        $this->ensureWindowsHelper();
        $line = is_array($this->windowsPipes) ? fgets($this->windowsPipes[1]) : false;

        if (! is_string($line)) {
            return 'escape';
        }

        [$code, $char] = array_map(intval(...), explode(',', trim($line)) + [0, 0]);

        return $this->keyFromCode($code, $char);
    }

    private function keyFromCode(int $code, int $char): string
    {
        return match ($code) {
            13 => 'enter',
            27 => 'escape',
            38 => 'up',
            40 => 'down',
            8 => 'backspace',
            32 => 'space',
            default => $char === 3 ? 'ctrl-c' : ($char >= 32 && $char <= 126 ? 'char:' . chr($char) : ''),
        };
    }

    private function enableRawMode(): void
    {
        if ($this->keyReader instanceof Closure || PHP_OS_FAMILY === 'Windows') {
            return;
        }

        $mode = shell_exec('stty -g 2>/dev/null');
        $this->sttyMode = is_string($mode) ? trim($mode) : null;
        shell_exec('stty -icanon -echo min 1 time 0 2>/dev/null');
    }

    private function restoreTerminal(): void
    {
        if (is_string($this->sttyMode) && $this->sttyMode !== '') {
            shell_exec('stty ' . escapeshellarg($this->sttyMode) . ' 2>/dev/null');
            $this->sttyMode = null;
        }

        if (is_resource($this->windowsProcess)) {
            proc_terminate($this->windowsProcess);
            proc_close($this->windowsProcess);
            $this->windowsProcess = null;
            $this->windowsPipes = null;
        }
    }

    private function ensureWindowsHelper(): void
    {
        if (is_resource($this->windowsProcess)) {
            return;
        }

        $script = <<<'PS'
while ($true) {
  $key = $Host.UI.RawUI.ReadKey('NoEcho,IncludeKeyDown')
  $code = [int] $key.VirtualKeyCode
  $char = [int] [char] $key.Character
  [Console]::Out.WriteLine("$code,$char")
  [Console]::Out.Flush()
}
PS;
        $process = proc_open(
            ['powershell', '-NoProfile', '-NoLogo', '-Command', $script],
            [
                0 => STDIN,
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (! is_resource($process)) {
            return;
        }

        $this->windowsProcess = $process;
        $this->windowsPipes = $pipes;
    }

    private function write(string $contents): void
    {
        if ($this->writer instanceof Closure) {
            ($this->writer)($contents);

            return;
        }

        fwrite(STDOUT, $contents);
        fflush(STDOUT);
    }
}
