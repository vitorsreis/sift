<?php

declare(strict_types=1);

namespace Sift\Output;

use Sift\Console\OutputPreferences;

final readonly class TerminalRenderer
{
    public function __construct(
        private PayloadSizer $payloadSizer = new PayloadSizer(),
        private HelpTerminalRenderer $helpRenderer = new HelpTerminalRenderer(),
        private ToolsListTerminalRenderer $toolsListRenderer = new ToolsListTerminalRenderer(),
        private SkillsFindTerminalRenderer $skillsFindRenderer = new SkillsFindTerminalRenderer(),
        private SkillsTerminalRenderer $skillsRenderer = new SkillsTerminalRenderer(),
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function render(array $payload, OutputPreferences $preferences): string
    {
        $style = $this->style($preferences);

        if (($payload['status'] ?? null) === 'error') {
            return $this->renderError($payload, $style);
        }

        $subcommand = $this->subcommand($payload);

        if ($subcommand === 'help') {
            return $this->helpRenderer->render($style) . PHP_EOL;
        }

        if ($subcommand === 'version') {
            return $this->renderVersion($payload, $style) . PHP_EOL;
        }

        if ($subcommand === 'tools list') {
            return $this->toolsListRenderer->render($payload, $style) . PHP_EOL;
        }

        if ($subcommand === 'skills find') {
            return $this->skillsFindRenderer->render($payload, $style) . PHP_EOL;
        }

        if (is_string($subcommand) && ($subcommand === 'skills' || str_starts_with($subcommand, 'skills '))) {
            return $this->skillsRenderer->render($payload, $style) . PHP_EOL;
        }

        return $this->renderPayload($this->payloadSizer->resize($payload, $preferences), $style) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function subcommand(array $payload): ?string
    {
        $meta = $payload['meta'] ?? null;

        if (! is_array($meta) || array_is_list($meta)) {
            return null;
        }

        $subcommand = $meta['subcommand'] ?? null;

        return is_string($subcommand) ? $subcommand : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderVersion(array $payload, TerminalStyle $style): string
    {
        $summary = $payload['summary'] ?? [];

        if (! is_array($summary) || array_is_list($summary)) {
            return 'Sift unknown';
        }

        return $style->bold('Sift') . ' ' . $style->cyan($this->value($summary['version'] ?? 'unknown'));
    }

    public function renderToolsListHeader(?OutputPreferences $preferences = null): string
    {
        return $this->toolsListRenderer->header($this->style($preferences));
    }

    /**
     * @param array<string, mixed> $item
     */
    public function renderToolsListItem(array $item, ?OutputPreferences $preferences = null): string
    {
        return $this->toolsListRenderer->item($item, $this->style($preferences));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPayload(array $payload, TerminalStyle $style): string
    {
        $lines = [$this->headline($payload, $style)];

        $summary = $payload['summary'] ?? null;
        if (is_array($summary) && ! array_is_list($summary)) {
            $lines[] = $style->label('summary:') . ' ' . $this->fields($summary, $style);
        }

        $flat = $this->flatFields($payload, $style);
        if ($flat !== '') {
            $lines[0] .= ' ' . $flat;
        }

        $this->appendListSection($lines, 'items', $payload['items'] ?? null, $style);
        $this->appendListSection($lines, 'artifacts', $payload['artifacts'] ?? null, $style);
        $this->appendObjectSection($lines, 'extra', $payload['extra'] ?? null, $style);
        $this->appendObjectSection($lines, 'meta', $payload['meta'] ?? null, $style);

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderError(array $payload, TerminalStyle $style): string
    {
        $error = $payload['error'] ?? [];
        $error = is_array($error) && ! array_is_list($error) ? $error : [];

        $code = $this->value($error['code'] ?? 'error');
        $message = $this->value($error['message'] ?? $code);
        $lines = [$style->errorBadge() . ' ' . $style->red($message)];
        $lines[] = $style->label('code:') . ' ' . $style->red($code);

        foreach (['hint'] as $key) {
            if (array_key_exists($key, $error)) {
                $lines[] = $style->label($key . ':') . ' ' . $this->value($error[$key]);
            }
        }

        foreach ($error as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (in_array($key, ['code', 'message', 'hint'], true)) {
                continue;
            }

            $lines[] = $style->label($key . ':') . ' ' . $this->value($value);
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function headline(array $payload, TerminalStyle $style): string
    {
        $tool = $payload['tool'] ?? 'sift';
        $status = $payload['status'] ?? 'passed';

        return $style->command($this->value($tool)) . ' ' . $style->status($this->value($status));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function flatFields(array $payload, TerminalStyle $style): string
    {
        $fields = [];

        foreach ($payload as $key => $value) {
            if (in_array($key, ['tool', 'status', 'summary', 'items', 'artifacts', 'extra', 'meta'], true)) {
                continue;
            }

            $fields[$key] = $value;
        }

        return $this->fields($fields, $style);
    }

    /**
     * @param array<mixed, mixed> $fields
     */
    private function fields(array $fields, TerminalStyle $style): string
    {
        $parts = [];

        foreach ($fields as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $parts[] = $style->label($key) . '=' . $this->value($value);
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<string> $lines
     */
    private function appendListSection(array &$lines, string $name, mixed $items, TerminalStyle $style): void
    {
        if (! is_array($items) || ! array_is_list($items) || $items === []) {
            return;
        }

        $lines[] = $style->label($name . ':');

        foreach ($items as $item) {
            $lines[] = '- ' . $this->listItem($item, $style);
        }
    }

    private function listItem(mixed $item, TerminalStyle $style): string
    {
        if (! is_array($item) || array_is_list($item)) {
            return $this->value($item);
        }

        $type = $this->value($item['type'] ?? 'item');
        $location = $this->location($item);
        $message = $this->value($item['message'] ?? '');
        $rest = [];

        foreach ($item as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (in_array($key, ['type', 'file', 'line', 'column', 'message'], true)) {
                continue;
            }

            $rest[$key] = $value;
        }

        return trim(implode(' ', array_filter([
            $type,
            $location,
            $message,
            $this->fields($rest, $style),
        ], static fn(string $part): bool => $part !== '')));
    }

    /**
     * @param array<mixed, mixed> $item
     */
    private function location(array $item): string
    {
        $file = $item['file'] ?? null;

        if (! is_string($file) || $file === '') {
            return '';
        }

        $location = $file;
        foreach (['line', 'column'] as $key) {
            $value = $item[$key] ?? null;
            if (is_int($value) || is_string($value) && $value !== '') {
                $location .= ':' . $value;
            }
        }

        return $location;
    }

    /**
     * @param list<string> $lines
     */
    private function appendObjectSection(array &$lines, string $name, mixed $values, TerminalStyle $style): void
    {
        if (! is_array($values) || array_is_list($values)) {
            return;
        }

        $lines[] = $style->label($name . ':') . ' ' . $this->fields($values, $style);
    }

    private function style(?OutputPreferences $preferences): TerminalStyle
    {
        return new TerminalStyle($preferences?->color() ?? true);
    }

    private function value(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
