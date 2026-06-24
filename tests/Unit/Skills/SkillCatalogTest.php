<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Skills\SkillCatalog;

it('normalizes skill catalog search results', function (): void {
    $items = (new SkillCatalog())->normalize([
        'items' => [
            [
                'name' => 'php-review',
                'description' => 'Review PHP projects.',
                'source' => 'vitorsreis/sift',
                'skills' => ['php-review', 'php-review', 'laravel-review'],
                'agents' => ['codex', 'cursor'],
                'tags' => ['php', 'review', 'php'],
            ],
        ],
    ]);

    expect($items)->toBe([
        [
            'name' => 'php-review',
            'description' => 'Review PHP projects.',
            'source' => 'vitorsreis/sift',
            'skills' => ['php-review', 'laravel-review'],
            'agents' => ['codex', 'cursor'],
            'tags' => ['php', 'review'],
        ],
    ]);
});

it('uses a safe fallback for catalog items without descriptions', function (): void {
    $items = (new SkillCatalog())->normalize([
        'items' => [
            [
                'name' => 'sift',
                'source' => 'vitorsreis/sift',
            ],
        ],
    ]);

    expect($items)->toBe([
        [
            'name' => 'sift',
            'description' => 'Use the sift skill.',
            'source' => 'vitorsreis/sift',
            'skills' => [],
            'agents' => [],
            'tags' => [],
        ],
    ]);
});

it('normalizes skills.sh search fields and orders by installs', function (): void {
    $items = (new SkillCatalog())->normalize([
        'skills' => [
            [
                'id' => 'github/awesome-copilot/php-mcp-server-generator',
                'skillId' => 'php-mcp-server-generator',
                'name' => 'php-mcp-server-generator',
                'installs' => 8621,
                'source' => 'github/awesome-copilot',
            ],
            [
                'id' => 'jeffallan/claude-skills/php-pro',
                'skillId' => 'php-pro',
                'name' => 'php-pro',
                'installs' => 11353,
                'source' => 'jeffallan/claude-skills',
            ],
        ],
    ]);

    expect($items)->toBe([
        [
            'name' => 'php-pro',
            'description' => 'Use the php-pro skill.',
            'source' => 'jeffallan/claude-skills',
            'skills' => [],
            'agents' => [],
            'tags' => [],
            'slug' => 'jeffallan/claude-skills/php-pro',
            'installs' => 11353,
        ],
        [
            'name' => 'php-mcp-server-generator',
            'description' => 'Use the php-mcp-server-generator skill.',
            'source' => 'github/awesome-copilot',
            'skills' => [],
            'agents' => [],
            'tags' => [],
            'slug' => 'github/awesome-copilot/php-mcp-server-generator',
            'installs' => 8621,
        ],
    ]);
});

it('rejects unexpected catalog result formats', function (): void {
    try {
        (new SkillCatalog())->normalize(['items' => [['name' => 'missing-source']]]);
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::SkillCatalogUnavailable);

        return;
    }

    throw new RuntimeException('Expected catalog format failure.');
});
