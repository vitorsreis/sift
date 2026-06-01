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

it('rejects unexpected catalog result formats', function (): void {
    try {
        (new SkillCatalog())->normalize(['items' => [['name' => 'missing-source']]]);
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::SkillCatalogUnavailable);

        return;
    }

    throw new RuntimeException('Expected catalog format failure.');
});
