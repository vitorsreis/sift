<?php

declare(strict_types=1);

use Sift\Console\InteractivePrompt;
use Sift\Skills\SkillCatalog;
use Sift\Skills\SkillsShCatalogClient;

function stripInteractiveAnsi(string $output): string
{
    return preg_replace('/\033\[[0-9;]*m/', '', $output) ?? $output;
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
    expect($plainOutput)->toContain('up/down navigate | enter install | esc cancel');
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
