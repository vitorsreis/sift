<?php

declare(strict_types=1);

use Sift\Skills\SkillManagedMetadata;

it('normalizes managed skill metadata payloads', function (): void {
    $metadata = SkillManagedMetadata::fromPayload([
        'name' => 'php-review',
        'source' => 'vendor/skills',
        'source_type' => 'local',
        'resolved_ref' => 'abc123',
        'installed_at' => '2026-06-01T00:00:00+00:00',
        'targets' => ['generic', 'generic'],
    ], 'fallback');

    expect($metadata)->toBeInstanceOf(SkillManagedMetadata::class);
    expect($metadata?->name())->toBe('php-review');
    expect($metadata?->source())->toBe('vendor/skills');
    expect($metadata?->targets())->toBe(['generic']);
    expect($metadata?->toItem())->toBe([
        'name' => 'php-review',
        'source' => 'vendor/skills',
        'source_type' => 'local',
        'resolved_ref' => 'abc123',
        'installed_at' => '2026-06-01T00:00:00+00:00',
        'targets' => ['generic'],
    ]);
});

it('uses the managed block fallback name when payload name is absent', function (): void {
    $metadata = SkillManagedMetadata::fromPayload([
        'source' => 'vendor/skills',
        'source_type' => 'local',
        'installed_at' => '2026-06-01T00:00:00+00:00',
        'targets' => ['generic'],
    ], 'php-review');

    expect($metadata?->name())->toBe('php-review');
});

it('rejects incomplete or invalid managed metadata', function (): void {
    /** @var list<array{payload: array<string, mixed>, fallback: string}> $cases */
    $cases = [
        [
            'payload' => [
                'name' => '../bad',
                'source' => 'vendor/skills',
                'source_type' => 'local',
                'installed_at' => '2026-06-01T00:00:00+00:00',
                'targets' => ['generic'],
            ],
            'fallback' => 'fallback',
        ],
        [
            'payload' => [
                'name' => 'php-review',
                'source_type' => 'local',
                'installed_at' => '2026-06-01T00:00:00+00:00',
                'targets' => ['generic'],
            ],
            'fallback' => 'fallback',
        ],
        [
            'payload' => [
                'name' => 'php-review',
                'source' => 'vendor/skills',
                'source_type' => 'local',
                'installed_at' => '2026-06-01T00:00:00+00:00',
            ],
            'fallback' => 'fallback',
        ],
    ];

    foreach ($cases as $case) {
        expect(SkillManagedMetadata::fromPayload($case['payload'], $case['fallback']))->toBeNull();
    }
});
