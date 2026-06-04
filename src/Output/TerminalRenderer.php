<?php

declare(strict_types=1);

namespace Sift\Output;

use Sift\Console\OutputPreferences;

final readonly class TerminalRenderer
{
    public function __construct(
        private PayloadSizer $payloadSizer = new PayloadSizer(),
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function render(array $payload, OutputPreferences $preferences): string
    {
        if (($payload['status'] ?? null) === 'error') {
            return $this->renderError($payload);
        }

        $subcommand = $this->subcommand($payload);

        if ($subcommand === 'help') {
            return $this->renderHelp() . PHP_EOL;
        }

        if ($subcommand === 'version') {
            return $this->renderVersion($payload) . PHP_EOL;
        }

        if ($subcommand === 'tools list') {
            return $this->renderToolsList($payload) . PHP_EOL;
        }

        return $this->renderPayload($this->payloadSizer->resize($payload, $preferences)) . PHP_EOL;
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

    private function renderHelp(): string
    {
        return str_replace("\n", PHP_EOL, <<<'TEXT'
Sift
  Agent tooling and skills layer for PHP projects.

Usage
  composer sift [options] <command> [args]
  composer sift [options] <tool> [args]
  composer skills [options] <command>

Commands
  <tool> [args]                Shortcut for run <tool>.
  run <tool> [args]            Run a tool through Sift.
  tools list                   List supported tools and local availability.

  history list                 List stored runs.
  history view <run_id>        Show a stored run.

  skills list                  List installed skills.
  skills add <source>          Install skills from a source.
  skills find [query]          Search available skills.
  skills init [name]           Scaffold a skill.
  skills remove <skill>        Remove installed skills.
  skills update [skill ...]    Update installed skills.

  init                         Create a sift.json config.
  validate                     Validate sift.json.
  version                      Show the installed Sift version.
  help                         Show this reference.

Options
  --json                       Render normalized JSON for supported commands.
  --no-json                    Force terminal output.
  --compact                    Keep result output short.
  --full                       Show complete result output.
  --pretty, -p                 Pretty-print JSON output.
  --raw                        Stream native tool output.
  --show-process               Show prepared process on STDERR.
  --history / --no-history     Force or skip history for a run.
  --config=<path>, -c <path>   Use a specific config file.

Terminal-only commands
  help, version, tools list     Always render terminal output.

Examples
  composer sift pest
  composer sift --compact phpstan analyse src
  composer sift --json --compact pest
  composer sift --no-json validate
  composer sift --full history view <run_id>
TEXT);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderVersion(array $payload): string
    {
        $summary = $payload['summary'] ?? [];

        if (! is_array($summary) || array_is_list($summary)) {
            return 'Sift unknown';
        }

        return 'Sift ' . $this->value($summary['version'] ?? 'unknown');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderToolsList(array $payload): string
    {
        $items = $payload['items'] ?? [];

        $lines = [rtrim($this->renderToolsListHeader(), PHP_EOL)];

        if (! is_array($items) || ! array_is_list($items) || $items === []) {
            $lines[] = '  No tools found.';

            return implode(PHP_EOL, $lines);
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (array_is_list($item)) {
                continue;
            }

            /** @var array<string, mixed> $item */
            $lines[] = rtrim($this->renderToolsListItem($item), PHP_EOL);
        }

        return implode(PHP_EOL, $lines);
    }

    public function renderToolsListHeader(): string
    {
        return 'Tools' . PHP_EOL
            . '  Supported tools and local availability.' . PHP_EOL
            . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $item
     */
    public function renderToolsListItem(array $item): string
    {
        $installed = ($item['installed'] ?? null) === true && ($item['enabled'] ?? null) === true;
        $status = $installed ? $this->green('OK') : $this->red('NO');
        $name = $this->toolDisplayName($this->value($item['tool'] ?? 'tool'));
        $version = $item['version'] ?? null;

        if ($installed) {
            $suffix = $this->toolVersion($version);

            return trim($status . ' ' . $name . ($suffix === '' ? '' : ' ' . $suffix)) . PHP_EOL;
        }

        $hint = $item['install_hint'] ?? null;
        $install = is_string($hint) && $hint !== '' ? ', use `' . $hint . '`' : '';

        return $status . ' ' . $name . $install . PHP_EOL;
    }

    private function toolVersion(mixed $version): string
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

    private function toolDisplayName(string $tool): string
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

    private function green(string $value): string
    {
        return "\033[32m" . $value . "\033[0m";
    }

    private function red(string $value): string
    {
        return "\033[31m" . $value . "\033[0m";
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPayload(array $payload): string
    {
        $lines = [$this->headline($payload)];

        $summary = $payload['summary'] ?? null;
        if (is_array($summary) && ! array_is_list($summary)) {
            $lines[] = 'summary: ' . $this->fields($summary);
        }

        $flat = $this->flatFields($payload);
        if ($flat !== '') {
            $lines[0] .= ' ' . $flat;
        }

        $this->appendListSection($lines, 'items', $payload['items'] ?? null);
        $this->appendListSection($lines, 'artifacts', $payload['artifacts'] ?? null);
        $this->appendObjectSection($lines, 'extra', $payload['extra'] ?? null);
        $this->appendObjectSection($lines, 'meta', $payload['meta'] ?? null);

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderError(array $payload): string
    {
        $error = $payload['error'] ?? [];
        $error = is_array($error) && ! array_is_list($error) ? $error : [];

        $code = $this->value($error['code'] ?? 'error');
        $lines = ['error ' . $code];

        foreach (['message', 'hint'] as $key) {
            if (array_key_exists($key, $error)) {
                $lines[] = $key . ': ' . $this->value($error[$key]);
            }
        }

        foreach ($error as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (in_array($key, ['code', 'message', 'hint'], true)) {
                continue;
            }

            $lines[] = $key . ': ' . $this->value($value);
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function headline(array $payload): string
    {
        $tool = $payload['tool'] ?? 'sift';
        $status = $payload['status'] ?? 'passed';

        return $this->value($tool) . ' ' . $this->value($status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function flatFields(array $payload): string
    {
        $fields = [];

        foreach ($payload as $key => $value) {
            if (in_array($key, ['tool', 'status', 'summary', 'items', 'artifacts', 'extra', 'meta'], true)) {
                continue;
            }

            $fields[$key] = $value;
        }

        return $this->fields($fields);
    }

    /**
     * @param array<mixed, mixed> $fields
     */
    private function fields(array $fields): string
    {
        $parts = [];

        foreach ($fields as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $parts[] = $key . '=' . $this->value($value);
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<string> $lines
     */
    private function appendListSection(array &$lines, string $name, mixed $items): void
    {
        if (! is_array($items) || ! array_is_list($items) || $items === []) {
            return;
        }

        $lines[] = $name . ':';

        foreach ($items as $item) {
            $lines[] = '- ' . $this->listItem($item);
        }
    }

    private function listItem(mixed $item): string
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
            $this->fields($rest),
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
    private function appendObjectSection(array &$lines, string $name, mixed $values): void
    {
        if (! is_array($values) || array_is_list($values)) {
            return;
        }

        $lines[] = $name . ': ' . $this->fields($values);
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
