<?php

declare(strict_types=1);

use Sift\Console\OutputPreferences;
use Sift\Console\OutputSize;
use Sift\Output\TerminalRenderer;
use Sift\Output\TerminalStyle;

function terminalRendererPreferences(OutputSize $size): OutputPreferences
{
    return new OutputPreferences(
        size: $size,
        pretty: false,
        showProcess: false,
        debug: false,
    );
}

function terminalRendererNoColorPreferences(OutputSize $size): OutputPreferences
{
    return new OutputPreferences(
        size: $size,
        pretty: false,
        showProcess: false,
        debug: false,
        color: false,
    );
}

function stripTerminalAnsi(string $output): string
{
    return preg_replace('/\033\[[0-9;]*m/', '', $output) ?? $output;
}

it('renders compact terminal output as one short line', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'pest',
        'status' => 'failed',
        'summary' => [
            'tests' => 12,
            'failures' => 1,
        ],
        'items' => [
            ['type' => 'test_failure', 'message' => 'Expected true to be false.'],
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toContain("\033[");
    expect(stripTerminalAnsi($output))->toBe('pest failed tests=12 failures=1' . PHP_EOL);
});

it('renders normal terminal output with summary and non-verbose items', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'pest',
        'status' => 'failed',
        'summary' => [
            'tests' => 12,
            'failures' => 1,
        ],
        'items' => [
            [
                'type' => 'test_failure',
                'message' => 'Expected true to be false.',
                'file' => 'tests/Feature/CheckoutTest.php',
                'line' => 42,
                'stdout' => 'verbose stdout',
            ],
        ],
    ], terminalRendererPreferences(OutputSize::Normal));

    expect($output)->toContain("\033[");
    expect(stripTerminalAnsi($output))->toBe(str_replace("\n", PHP_EOL, <<<'TEXT'
pest failed
summary: tests=12 failures=1
items:
- test_failure tests/Feature/CheckoutTest.php:42 Expected true to be false.
TEXT) . PHP_EOL);
});

it('renders terminal output without ansi when colors are disabled', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'pest',
        'status' => 'failed',
        'summary' => [
            'tests' => 12,
            'failures' => 1,
        ],
    ], terminalRendererNoColorPreferences(OutputSize::Compact));

    expect($output)->toBe('pest failed tests=12 failures=1' . PHP_EOL);
    expect($output)->not->toContain("\033[");
});

it('renders version terminal output as a short version line', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => [
            'version' => '2.0.0',
        ],
        'items' => [],
        'artifacts' => [],
        'extra' => [],
        'meta' => [
            'subcommand' => 'version',
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toContain("\033[");
    expect(stripTerminalAnsi($output))->toBe('Sift 2.0.0' . PHP_EOL);
});

it('renders tools list terminal output as status lines', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => ['supported' => 2, 'installed' => 1, 'enabled' => 2],
        'items' => [
            [
                'tool' => 'pest',
                'enabled' => true,
                'installed' => true,
                'version' => 'Pest 4.0.1',
            ],
            [
                'tool' => 'phpunit',
                'enabled' => true,
                'installed' => false,
                'version' => null,
                'install_hint' => 'composer require --dev phpunit/phpunit',
            ],
        ],
        'meta' => ['subcommand' => 'tools list'],
    ], terminalRendererPreferences(OutputSize::Compact));

    $plainOutput = stripTerminalAnsi($output);

    expect($plainOutput)->toContain('Supported tools and local availability.');
    expect($plainOutput)->toContain('OK');
    expect($plainOutput)->toContain('Pest 4.0.1');
    expect($plainOutput)->toContain('NO');
    expect($plainOutput)->toContain('PHPUnit, use `composer require --dev phpunit/phpunit`');
});

it('renders skills find terminal output like the skills cli', function (): void {
    $branch = "\u{2514}";
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => ['total' => 2],
        'items' => [
            [
                'name' => 'php-pro',
                'source' => 'jeffallan/claude-skills',
                'slug' => 'jeffallan/claude-skills/php-pro',
                'installs' => 11353,
            ],
            [
                'name' => 'php-mcp-server-generator',
                'source' => 'github/awesome-copilot',
                'slug' => 'github/awesome-copilot/php-mcp-server-generator',
                'installs' => 8621,
            ],
        ],
        'artifacts' => [],
        'extra' => [],
        'meta' => [
            'subcommand' => 'skills find',
            'query' => 'php',
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toContain("\033[38;5;");
    expect(stripTerminalAnsi($output))->toBe(str_replace("\n", PHP_EOL, <<<TEXT
Install with composer skills add <owner/repo@skill>

jeffallan/claude-skills@php-pro 11.4K installs
{$branch} https://skills.sh/jeffallan/claude-skills/php-pro

github/awesome-copilot@php-mcp-server-generator 8.6K installs
{$branch} https://skills.sh/github/awesome-copilot/php-mcp-server-generator
TEXT) . PHP_EOL);
});

it('renders skills find guidance instead of generic payload text without a query', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => ['total' => 0],
        'items' => [],
        'artifacts' => [],
        'extra' => [],
        'meta' => [
            'subcommand' => 'skills find',
            'query' => '',
            'mode' => 'agent_tip',
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toContain("\033[");
    expect(stripTerminalAnsi($output))->toBe(str_replace("\n", PHP_EOL, <<<'TEXT'
Tip: if running in a coding agent, follow these steps:
  1) composer skills find [query] [--owner <owner>]
  2) composer skills add <owner/repo@skill>

Usage: composer skills find [query] [--owner <owner>]
TEXT) . PHP_EOL);
});

it('renders skills root help like the skills cli', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => [
            'command' => 'skills',
            'description' => 'Manage Sift agent skills.',
        ],
        'items' => [],
        'artifacts' => [],
        'extra' => [],
        'meta' => [
            'subcommand' => 'skills',
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toContain("\033[38;5;");

    $plainOutput = stripTerminalAnsi($output);

    expect($plainOutput)->toStartWith('███████╗██╗███████╗████████╗    ███████╗██╗  ██╗██╗██╗     ██╗     ███████╗');
    expect($plainOutput)->toContain('Usage: composer skills <command> [options]');
    expect($plainOutput)->toContain('Manage Skills:');
    expect($plainOutput)->toContain('add <package>');
    expect($plainOutput)->toContain('find [query]');
    expect($plainOutput)->toContain('Updates:');
    expect($plainOutput)->toContain('Project:');
    expect($plainOutput)->toContain('Remove Options:');
    expect($plainOutput)->toContain('Update Options:');
    expect($plainOutput)->toContain('List Options:');
    expect($plainOutput)->toContain('Examples:');
    expect($plainOutput)->toContain('Discover more skills at https://skills.sh/');
    expect($plainOutput)->toContain('--no-color');
    expect($output)->not->toContain('summary:');
    expect($output)->not->toContain('meta:');
});

it('renders the skills banner with a vertical grayscale gradient', function (): void {
    $banner = (new TerminalStyle())->siftSkillsBanner();
    $colors = [];

    foreach ($banner as $line) {
        preg_match_all('/\033\[38;5;(\d+)m/', $line, $matches);

        expect($matches[1])->not->toBeEmpty();
        expect(array_unique($matches[1]))->toHaveCount(1);

        $colors[] = (int) $matches[1][0];
    }

    expect(array_unique($colors))->toHaveCount(count($banner));
    expect($colors)->toBe([250, 248, 246, 244, 242, 240]);
});

it('renders skills root help without ansi when colors are disabled', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => [
            'command' => 'skills',
            'description' => 'Manage Sift agent skills.',
        ],
        'items' => [],
        'artifacts' => [],
        'extra' => [],
        'meta' => [
            'subcommand' => 'skills',
        ],
    ], terminalRendererNoColorPreferences(OutputSize::Compact));

    expect($output)->toStartWith('███████╗██╗███████╗████████╗    ███████╗██╗  ██╗██╗██╗     ██╗     ███████╗');
    expect($output)->not->toContain("\033[");
});

it('renders skills list terminal output like the skills cli', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => ['total' => 1],
        'items' => [
            [
                'name' => 'php-review',
                'source' => 'owner/repo',
                'source_type' => 'github',
                'resolved_ref' => 'abc123',
                'installed_at' => '2026-06-25T10:00:00+00:00',
                'targets' => ['codex', 'generic'],
            ],
        ],
        'artifacts' => [],
        'extra' => [],
        'meta' => [
            'subcommand' => 'skills list',
            'targets' => ['codex', 'generic'],
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toContain("\033[");
    expect(stripTerminalAnsi($output))->toContain('Project Skills');
    expect($output)->toContain('php-review');
    expect($output)->toContain('owner/repo');
    expect(stripTerminalAnsi($output))->toContain('Agents: codex, generic');
    expect($output)->not->toContain('summary:');
    expect($output)->not->toContain('meta:');
});

it('renders empty skills list without generic payload fields', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => ['total' => 0],
        'items' => [],
        'artifacts' => [],
        'extra' => [],
        'meta' => [
            'subcommand' => 'skills list',
            'targets' => ['codex'],
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect(stripTerminalAnsi($output))->toContain('Project Skills');
    expect(stripTerminalAnsi($output))->toContain('No project skills installed.');
    expect($output)->not->toContain('summary:');
});

it('renders skills add list previews like the skills cli', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => ['total' => 1],
        'items' => [
            [
                'name' => 'php-review',
                'description' => 'Use when reviewing PHP.',
                'source' => 'owner/repo',
                'source_type' => 'github',
                'path' => 'skills/php-review',
            ],
        ],
        'artifacts' => [],
        'extra' => [],
        'meta' => [
            'subcommand' => 'skills add --list',
            'source' => 'owner/repo',
            'source_type' => 'github',
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toContain("\033[");
    expect(stripTerminalAnsi($output))->toContain('Available Skills');
    expect($output)->toContain('php-review');
    expect($output)->toContain('Use when reviewing PHP.');
    expect(stripTerminalAnsi($output))->toContain('Install with composer skills add owner/repo --skill php-review');
    expect($output)->not->toContain('summary:');
});

it('renders skills install remove update and init result output without generic payload fields', function (string $subcommand, array $summary, string $expected): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => $summary,
        'items' => [
            [
                'name' => 'php-review',
                'target' => 'codex',
                'path' => '.codex/skills/php-review',
                'action' => 'installed',
            ],
        ],
        'artifacts' => [],
        'extra' => [],
        'meta' => [
            'subcommand' => $subcommand,
            'targets' => ['codex'],
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toContain("\033[");
    expect(stripTerminalAnsi($output))->toContain($expected);
    expect($output)->toContain('php-review');
    expect($output)->toContain('codex');
    if ($subcommand !== 'skills init') {
        expect(stripTerminalAnsi($output))->toContain('codex: installed');
    }

    expect($output)->not->toContain('summary:');
    expect($output)->not->toContain('meta:');
})->with([
    ['skills add', ['installed' => 1, 'skills' => 1, 'targets' => 1], 'Installed Skills'],
    ['skills remove', ['removed' => 1], 'Removed Skills'],
    ['skills update', ['updated' => 1, 'skills' => 1, 'targets' => 1], 'Updated Skills'],
    ['skills init', ['created' => 1], 'Initialized skill'],
]);

it('renders skills init with next steps like the skills cli', function (): void {
    $output = (new TerminalRenderer())->render([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => ['created' => 1],
        'items' => [
            [
                'name' => 'demo-skill',
                'path' => 'D:\\Work\\projects\\others\\sift\\demo-skill\\SKILL.md',
                'action' => 'created',
            ],
        ],
        'artifacts' => [],
        'extra' => [],
        'meta' => [
            'subcommand' => 'skills init',
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toContain("\033[");
    expect(stripTerminalAnsi($output))->toContain('Initialized skill: demo-skill');
    expect(stripTerminalAnsi($output))->toContain('Created:');
    expect($output)->toContain('demo-skill/SKILL.md');
    expect(stripTerminalAnsi($output))->toContain('Next steps:');
    expect(stripTerminalAnsi($output))->toContain('Publishing:');
    expect($output)->not->toContain('D:\\Work\\projects\\others\\sift');
});

it('renders errors with code message hint and context', function (): void {
    $output = (new TerminalRenderer())->render([
        'status' => 'error',
        'error' => [
            'code' => 'invalid_usage',
            'message' => 'Unknown option "--bad".',
            'hint' => 'Run "sift help" to list available commands.',
            'argument' => '--bad',
        ],
    ], terminalRendererPreferences(OutputSize::Compact));

    expect($output)->toContain("\033[41m");
    expect(stripTerminalAnsi($output))->toBe(str_replace("\n", PHP_EOL, <<<'TEXT'
ERROR Unknown option "--bad".
code: invalid_usage
hint: Run "sift help" to list available commands.
argument: --bad
TEXT) . PHP_EOL);
});
