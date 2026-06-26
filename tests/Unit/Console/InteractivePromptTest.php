<?php

declare(strict_types=1);

use Sift\Console\InteractivePrompt;
use Sift\Skills\SkillCatalog;
use Sift\Skills\SkillsShCatalogClient;

function stripInteractiveAnsi(string $output): string
{
    return preg_replace('/\033\[[0-9;?]*[A-Za-z]/', '', $output) ?? $output;
}

function lastInteractiveFrame(string $output): string
{
    $frames = explode("\033[J", $output);
    $frame = $frames[count($frames) - 1];

    return $frame !== '' ? $frame : $output;
}

it('searches as the user types and returns the highlighted skill', function (): void {
    $keys = ['char:p', 'char:h', 'idle', 'down', 'enter'];
    $output = '';
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (string $contents) use (&$output): void {
            $output .= $contents;
        },
    );
    $catalog = new SkillCatalog(new SkillsShCatalogClient(
        fetcher: static fn(string $url, int $timeout, array $headers): array => [
            'status' => 200,
            'body' => json_encode([
                'skills' => [
                    [
                        'id' => 'github/awesome-copilot/php-mcp-server-generator',
                        'name' => 'php-mcp-server-generator',
                        'installs' => 8621,
                        'source' => 'github/awesome-copilot',
                    ],
                    [
                        'id' => 'jeffallan/claude-skills/php-pro',
                        'name' => 'php-pro',
                        'installs' => 11353,
                        'source' => 'jeffallan/claude-skills',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'error' => null,
        ],
    ));

    $selected = $prompt->searchSkills($catalog);

    expect($selected['name'] ?? null)->toBe('php-mcp-server-generator');
    expect($output)->toContain("\033[38;5;");

    $plainOutput = stripInteractiveAnsi($output);

    expect($plainOutput)->toContain('███████╗██╗███████╗████████╗    ███████╗██╗  ██╗██╗██╗     ██╗     ███████╗');
    expect($plainOutput)->not->toContain('------');
    expect($plainOutput)->toContain('Search skills: ph_');
    expect($plainOutput)->toContain('up/down navigate | enter select | esc cancel');
});

it('can render interactive search without ansi colors', function (): void {
    $keys = ['escape'];
    $output = '';
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (string $contents) use (&$output): void {
            $output .= $contents;
        },
    );
    $catalog = new SkillCatalog(new SkillsShCatalogClient(
        fetcher: static function (): array {
            throw new RuntimeException('Catalog should not be queried.');
        },
    ));

    $selected = $prompt->searchSkills($catalog, color: false);

    expect($selected)->toBeNull();
    expect($output)->toContain('███████╗██╗███████╗████████╗    ███████╗██╗  ██╗██╗██╗     ██╗     ███████╗');
    expect($output)->not->toContain("\033[38;5;");
});

it('debounces typeahead searches instead of blocking on every typed character', function (): void {
    $keys = ['char:p', 'char:h', 'char:p', 'idle', 'enter'];
    $urls = [];
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (): void {},
    );
    $catalog = new SkillCatalog(new SkillsShCatalogClient(
        fetcher: static function (string $url, int $timeout, array $headers) use (&$urls): array {
            unset($timeout, $headers);
            $urls[] = $url;

            return [
                'status' => 200,
                'body' => json_encode([
                    'skills' => [
                        [
                            'id' => 'jeffallan/claude-skills/php-pro',
                            'name' => 'php-pro',
                            'installs' => 11353,
                            'source' => 'jeffallan/claude-skills',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'error' => null,
            ];
        },
    ));

    $selected = $prompt->searchSkills($catalog);

    expect($selected['name'] ?? null)->toBe('php-pro');
    expect($urls)->toHaveCount(1);
    expect($urls[0])->toContain('q=php');
});

it('waits long enough before searching a partial typed query', function (): void {
    $method = new ReflectionMethod(InteractivePrompt::class, 'searchDebounceSeconds');

    expect($method->invoke(new InteractivePrompt(), 'ca'))->toBeGreaterThanOrEqual(0.3);
});

it('ignores enter while the typed search is still pending', function (): void {
    $keys = ['char:p', 'char:h', 'enter', 'escape'];
    $urls = [];
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (): void {},
    );
    $catalog = new SkillCatalog(new SkillsShCatalogClient(
        fetcher: static function (string $url) use (&$urls): array {
            $urls[] = $url;

            return [
                'status' => 200,
                'body' => json_encode([
                    'skills' => [
                        [
                            'id' => 'jeffallan/claude-skills/php-pro',
                            'name' => 'php-pro',
                            'installs' => 11353,
                            'source' => 'jeffallan/claude-skills',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'error' => null,
            ];
        },
    ));

    $selected = $prompt->searchSkills($catalog);

    expect($selected)->toBeNull();
    expect($urls)->toBe([]);
});

it('does not force a pending search when navigating with arrow keys', function (): void {
    $keys = ['char:p', 'char:h', 'down', 'escape'];
    $urls = [];
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (): void {},
    );
    $catalog = new SkillCatalog(new SkillsShCatalogClient(
        fetcher: static function (string $url) use (&$urls): array {
            $urls[] = $url;

            return [
                'status' => 200,
                'body' => json_encode([
                    'skills' => [
                        [
                            'id' => 'jeffallan/claude-skills/php-pro',
                            'name' => 'php-pro',
                            'installs' => 11353,
                            'source' => 'jeffallan/claude-skills',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'error' => null,
            ];
        },
    ));

    $selected = $prompt->searchSkills($catalog);

    expect($selected)->toBeNull();
    expect($urls)->toBe([]);
});

it('keeps keyboard selection on visible search results', function (): void {
    $keys = ['char:p', 'char:h', 'idle'];
    $keys = [...$keys, ...array_fill(0, 12, 'down'), 'enter'];

    $output = '';
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (string $contents) use (&$output): void {
            $output .= $contents;
        },
    );
    $catalog = new SkillCatalog(new SkillsShCatalogClient(
        fetcher: static fn(): array => [
            'status' => 200,
            'body' => json_encode([
                'skills' => array_map(
                    static fn(int $index): array => [
                        'id' => sprintf('owner/repo/skill-%02d', $index),
                        'name' => sprintf('skill-%02d', $index),
                        'installs' => $index,
                        'source' => 'owner/repo',
                    ],
                    range(1, 10),
                ),
            ], JSON_THROW_ON_ERROR),
            'error' => null,
        ],
    ));

    $selected = $prompt->searchSkills($catalog);

    expect($selected['name'] ?? null)->toBe('skill-01');
    expect(stripInteractiveAnsi($output))->toContain('> skill-01');
});

it('supports keyboard multiselect prompts', function (): void {
    $keys = ['down', 'space', 'enter'];
    $output = '';
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (string $contents) use (&$output): void {
            $output .= $contents;
        },
    );

    $selected = $prompt->multiselect('Which agents do you want to install to?', [
        ['value' => 'codex', 'label' => 'codex', 'selected' => true],
        ['value' => 'generic', 'label' => 'generic'],
    ]);

    expect($selected)->toBe(['codex', 'generic']);
    expect($output)->toContain("\033[38;5;");
    expect(stripInteractiveAnsi($output))->toContain('███████╗██╗███████╗████████╗    ███████╗██╗  ██╗██╗██╗     ██╗     ███████╗');
    expect(stripInteractiveAnsi($output))->not->toContain('------');
});

it('selects the highlighted multiselect option when enter is pressed with nothing checked', function (): void {
    $keys = ['down', 'enter'];
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (): void {},
    );

    $selected = $prompt->multiselect('Which agents do you want to install to?', [
        ['value' => 'standard', 'label' => 'standard'],
        ['value' => 'generic', 'label' => 'generic'],
    ]);

    expect($selected)->toBe(['generic']);
});

it('selects the highlighted multiselect option after navigating away from defaults', function (): void {
    $keys = ['down', 'enter'];
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (): void {},
    );

    $selected = $prompt->multiselect('Which agents do you want to install to?', [
        ['value' => 'standard', 'label' => 'standard', 'selected' => true],
        ['value' => 'generic', 'label' => 'generic'],
    ]);

    expect($selected)->toBe(['generic']);
});

it('limits rendered multiselect options while keeping navigation across all choices', function (): void {
    $keys = [...array_fill(0, 12, 'down'), 'space', 'enter'];
    $output = '';
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (string $contents) use (&$output): void {
            $output .= $contents;
        },
    );

    $selected = $prompt->multiselect(
        'Which agents do you want to install to?',
        array_map(
            static fn(int $index): array => [
                'value' => sprintf('agent-%02d', $index),
                'label' => sprintf('agent-%02d', $index),
            ],
            range(1, 15),
        ),
        color: false,
    );

    $frame = stripInteractiveAnsi(lastInteractiveFrame($output));

    expect($selected)->toBe(['agent-13']);
    expect($frame)->toContain('showing 6-15 of 15');
    expect($frame)->toContain('> [x] agent-13');
    expect($frame)->not->toContain('agent-01');
    expect($frame)->not->toContain('agent-05');
});

it('supports keyboard select prompts with enter on the highlighted option', function (): void {
    $keys = ['down', 'enter'];
    $output = '';
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (string $contents) use (&$output): void {
            $output .= $contents;
        },
    );

    $selected = $prompt->select('Installation scope', [
        ['value' => 'project', 'label' => 'Project', 'hint' => 'Current directory'],
        ['value' => 'global', 'label' => 'Global', 'hint' => 'Home directory'],
    ]);

    expect($selected)->toBe('global');
    expect(stripInteractiveAnsi($output))->toContain('Installation scope');
    expect(stripInteractiveAnsi($output))->toContain('up/down navigate | enter continue | esc cancel');
});

it('accepts enter as the default interactive confirmation', function (): void {
    $keys = ['enter'];
    $output = '';
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (string $contents) use (&$output): void {
            $output .= $contents;
        },
    );

    $confirmed = $prompt->confirm('Install skill sift?', color: false);

    expect($confirmed)->toBeTrue();
    expect($output)->toContain('Install skill sift? [Y/n] ');
});
