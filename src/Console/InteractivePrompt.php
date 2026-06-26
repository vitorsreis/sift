<?php

declare(strict_types=1);

namespace Sift\Console;

use Closure;
use Sift\Output\TerminalStyle;
use Sift\Skills\SkillCatalog;

final class InteractivePrompt
{
    private const int SEARCH_RESULT_LIMIT = 10;

    private const int MULTISELECT_VISIBLE_LIMIT = 10;

    private const float SEARCH_DEBOUNCE_SECONDS = 0.35;

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
    public function searchSkills(SkillCatalog $catalog, ?string $owner = null, bool $color = true): ?array
    {
        return $this->withTerminal(function () use ($catalog, $owner, $color): ?array {
            $style = new TerminalStyle($color);
            $state = new InteractiveSearchState();

            $render = function () use ($state, $style): void {
                if ($state->lastRenderedLines > 0) {
                    $this->write("\033[" . $state->lastRenderedLines . "A\033[1G");
                }

                $lines = $this->searchLines($state->query, $state->results, $state->selected, $state->loading, $style);
                $this->write(self::CLEAR_DOWN . implode(PHP_EOL, $lines) . PHP_EOL);
                $state->lastRenderedLines = count($lines);
            };

            $render();

            while (true) {
                $timeout = is_float($state->debounceUntil) ? max(0.0, $state->debounceUntil - microtime(true)) : null;
                $key = $this->readKey($timeout);

                if ($key === 'idle') {
                    $this->flushSearch($state, $catalog, $owner, $render, true);

                    continue;
                }

                if ($key === 'escape' || $key === 'ctrl-c') {
                    return null;
                }

                if ($key === 'enter') {
                    if ($state->hasPendingSearch()) {
                        continue;
                    }

                    return $state->results[$state->selected] ?? null;
                }

                if ($key === 'up') {
                    if ($state->hasPendingSearch()) {
                        continue;
                    }

                    $state->selected = max(0, $state->selected - 1);
                    $render();

                    continue;
                }

                if ($key === 'down') {
                    if ($state->hasPendingSearch()) {
                        continue;
                    }

                    $lastVisibleIndex = min(self::SEARCH_RESULT_LIMIT, count($state->results)) - 1;
                    $state->selected = min(max(0, $lastVisibleIndex), $state->selected + 1);
                    $render();

                    continue;
                }

                if ($key === 'backspace') {
                    $state->query = substr($state->query, 0, -1);
                } elseif (str_starts_with($key, 'char:')) {
                    $state->query .= substr($key, 5);
                } else {
                    continue;
                }

                $this->queueSearch($state, $render);
            }
        });
    }

    /**
     * @param list<array{value: string, label: string, hint?: string, selected?: bool}> $options
     *
     * @return list<string>|null
     */
    public function multiselect(string $message, array $options, bool $color = true): ?array
    {
        return $this->withTerminal(function () use ($message, $options, $color): ?array {
            $style = new TerminalStyle($color);
            $cursor = 0;
            $selected = [];
            $navigated = false;
            $selectionTouched = false;
            $lastRenderedLines = 0;

            foreach ($options as $option) {
                if (($option['selected'] ?? false) === true) {
                    $selected[$option['value']] = true;
                }
            }

            $render = function () use (&$lastRenderedLines, $message, $options, &$cursor, &$selected, $style): void {
                if ($lastRenderedLines > 0) {
                    $this->write("\033[" . $lastRenderedLines . "A\033[1G");
                }

                $lines = [...$this->headerLines($style), $style->label($message)];
                $totalOptions = count($options);
                $visibleStart = 0;

                if ($totalOptions > self::MULTISELECT_VISIBLE_LIMIT) {
                    $visibleStart = min(
                        max(0, $cursor - intdiv(self::MULTISELECT_VISIBLE_LIMIT, 2)),
                        $totalOptions - self::MULTISELECT_VISIBLE_LIMIT,
                    );
                }

                foreach (array_slice($options, $visibleStart, self::MULTISELECT_VISIBLE_LIMIT, true) as $index => $option) {
                    $marker = isset($selected[$option['value']]) ? $style->success('[x]') : $style->muted('[ ]');
                    $pointer = $index === $cursor ? $style->command('>') : ' ';
                    $hint = isset($option['hint']) && $option['hint'] !== '' ? ' - ' . $style->muted($option['hint']) : '';
                    $lines[] = sprintf('  %s %s %s%s', $pointer, $marker, $style->command($option['label']), $hint);
                }

                if ($totalOptions > self::MULTISELECT_VISIBLE_LIMIT) {
                    $lines[] = $style->muted(sprintf(
                        'showing %d-%d of %d',
                        $visibleStart + 1,
                        min($totalOptions, $visibleStart + self::MULTISELECT_VISIBLE_LIMIT),
                        $totalOptions,
                    ));
                }

                $lines[] = '';
                $lines[] = $style->muted('up/down navigate | space toggle | enter select/continue | esc cancel');

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
                    $navigated = true;
                    $render();

                    continue;
                }

                if ($key === 'down') {
                    $cursor = min(max(0, count($options) - 1), $cursor + 1);
                    $navigated = true;
                    $render();

                    continue;
                }

                if ($key === 'space') {
                    if (! isset($options[$cursor])) {
                        continue;
                    }

                    $value = $options[$cursor]['value'];
                    if (isset($selected[$value])) {
                        unset($selected[$value]);
                    } else {
                        $selected[$value] = true;
                    }

                    $selectionTouched = true;
                    $render();

                    continue;
                }

                if ($key === 'enter') {
                    if (! isset($options[$cursor])) {
                        continue;
                    }

                    if ($selectionTouched && $selected !== []) {
                        return array_keys($selected);
                    }

                    if ($navigated || $selected === []) {
                        return [$options[$cursor]['value']];
                    }

                    return array_keys($selected);
                }
            }
        });
    }

    /**
     * @param list<array{value: string, label: string, hint?: string}> $options
     */
    public function select(string $message, array $options, bool $color = true): ?string
    {
        return $this->withTerminal(function () use ($message, $options, $color): ?string {
            $style = new TerminalStyle($color);
            $cursor = 0;
            $lastRenderedLines = 0;

            $render = function () use (&$lastRenderedLines, $message, $options, &$cursor, $style): void {
                if ($lastRenderedLines > 0) {
                    $this->write("\033[" . $lastRenderedLines . "A\033[1G");
                }

                $lines = [...$this->headerLines($style), $style->label($message)];

                foreach ($options as $index => $option) {
                    $pointer = $index === $cursor ? $style->command('>') : ' ';
                    $hint = isset($option['hint']) && $option['hint'] !== '' ? ' - ' . $style->muted($option['hint']) : '';
                    $lines[] = sprintf('  %s %s%s', $pointer, $style->command($option['label']), $hint);
                }

                $lines[] = '';
                $lines[] = $style->muted('up/down navigate | enter continue | esc cancel');

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

                if ($key === 'enter') {
                    return $options[$cursor]['value'] ?? null;
                }
            }
        });
    }

    public function confirm(string $message, bool $color = true): bool
    {
        return $this->withTerminal(function () use ($message, $color): bool {
            $style = new TerminalStyle($color);
            $this->write($style->label($message) . ' ' . $style->argument('[y/N]') . ' ');

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
    private function searchLines(string $query, array $results, int $selected, bool $loading, TerminalStyle $style): array
    {
        $lines = [
            ...$this->headerLines($style),
            $style->label('Search skills:') . ' ' . $style->argument($query . '_'),
            '',
        ];

        if (strlen($query) < 2) {
            $lines[] = $style->muted('Start typing to search (min 2 chars)');
        } elseif ($results === [] && $loading) {
            $lines[] = $style->warning('Searching...');
        } elseif ($results === []) {
            $lines[] = $style->warning('No skills found');
        } else {
            foreach (array_slice($results, 0, self::SEARCH_RESULT_LIMIT) as $index => $skill) {
                $name = is_string($skill['name'] ?? null) ? $skill['name'] : '';
                $source = is_string($skill['source'] ?? null) ? $skill['source'] : '';
                $installs = $this->formatInstalls($skill['installs'] ?? null);
                $pointer = $index === $selected ? $style->command('>') : ' ';
                $lines[] = sprintf(
                    '  %s %s %s%s',
                    $pointer,
                    $style->command($name),
                    $style->muted($source),
                    $installs === '' ? '' : ' ' . $style->muted($installs),
                );
            }
        }

        $lines[] = '';
        $lines[] = $style->muted('up/down navigate | enter select | esc cancel');

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
     * @return list<string>
     */
    private function headerLines(TerminalStyle $style): array
    {
        return [
            ...$style->siftSkillsBanner(),
            '',
        ];
    }

    private function searchDebounceSeconds(string $query): float
    {
        return strlen($query) < 2 ? 0.0 : self::SEARCH_DEBOUNCE_SECONDS;
    }

    /**
     * @param Closure(): void $render
     */
    private function queueSearch(InteractiveSearchState $state, Closure $render): void
    {
        if (strlen($state->query) < 2) {
            $state->results = [];
            $state->selected = 0;
            $state->pendingQuery = null;
            $state->debounceUntil = null;
            $state->loading = false;
            $state->lastSearchedQuery = null;
            $render();

            return;
        }

        $state->pendingQuery = $state->query;
        $state->debounceUntil = microtime(true) + $this->searchDebounceSeconds($state->query);
        $state->loading = true;
        $render();
    }

    /**
     * @param Closure(): void $render
     */
    private function flushSearch(
        InteractiveSearchState $state,
        SkillCatalog $catalog,
        ?string $owner,
        Closure $render,
        bool $force = false,
    ): void {
        if ($state->pendingQuery === null) {
            return;
        }

        if (! $force && $state->debounceUntil !== null && microtime(true) < $state->debounceUntil) {
            return;
        }

        $searchQuery = $state->pendingQuery;
        $state->pendingQuery = null;
        $state->debounceUntil = null;

        if ($searchQuery !== $state->lastSearchedQuery) {
            $state->lastSearchedQuery = $searchQuery;
            $cacheKey = ($owner ?? '') . "\0" . $searchQuery;
            $state->results = $state->cache[$cacheKey] ??= $catalog->search($searchQuery, $owner);
            $state->selected = 0;
        }

        $state->loading = false;
        $render();
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

    private function readKey(?float $timeoutSeconds = null): string
    {
        if ($this->keyReader instanceof Closure) {
            return ($this->keyReader)();
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->readWindowsKey($timeoutSeconds);
        }

        return $this->readUnixKey($timeoutSeconds);
    }

    private function readUnixKey(?float $timeoutSeconds = null): string
    {
        if ($timeoutSeconds !== null && ! $this->stdinReady($timeoutSeconds)) {
            return 'idle';
        }

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

    private function readWindowsKey(?float $timeoutSeconds = null): string
    {
        $this->ensureWindowsHelper();

        if (! is_array($this->windowsPipes)) {
            return 'escape';
        }

        $pipe = $this->windowsPipes[1];
        stream_set_blocking($pipe, true);

        if ($timeoutSeconds !== null) {
            $read = [$pipe];
            $write = null;
            $except = null;
            $seconds = (int) floor($timeoutSeconds);
            $microseconds = (int) max(0, ($timeoutSeconds - $seconds) * 1000000);
            $ready = @stream_select($read, $write, $except, $seconds, $microseconds);

            if ($ready !== 1) {
                return 'idle';
            }
        }

        $line = fgets($pipe);

        if (! is_string($line)) {
            return $timeoutSeconds === null ? 'escape' : 'idle';
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

    private function stdinReady(float $timeoutSeconds): bool
    {
        $read = [STDIN];
        $write = null;
        $except = null;
        $seconds = (int) floor($timeoutSeconds);
        $microseconds = (int) max(0, ($timeoutSeconds - $seconds) * 1000000);
        $ready = @stream_select($read, $write, $except, $seconds, $microseconds);

        return $ready === 1;
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
