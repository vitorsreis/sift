<?php

declare(strict_types=1);

use Sift\Console\InteractivePrompt;
use Sift\Skills\SkillCatalog;
use Sift\Skills\SkillsShCatalogClient;

it('searches as the user types and returns the highlighted skill', function (): void {
    $keys = ['char:p', 'char:h', 'down', 'enter'];
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
    expect($output)->toContain('Search skills: ph_');
    expect($output)->toContain('up/down navigate | enter install | esc cancel');
});

it('supports keyboard multiselect prompts', function (): void {
    $keys = ['down', 'space', 'enter'];
    $prompt = new InteractivePrompt(
        keyReader: static function () use (&$keys): string {
            return array_shift($keys) ?? 'escape';
        },
        writer: static function (): void {},
    );

    $selected = $prompt->multiselect('Which agents do you want to install to?', [
        ['value' => 'codex', 'label' => 'codex', 'selected' => true],
        ['value' => 'generic', 'label' => 'generic'],
    ]);

    expect($selected)->toBe(['codex', 'generic']);
});
