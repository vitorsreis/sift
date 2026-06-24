<?php

declare(strict_types=1);

use Sift\Skills\ClonedSkillSource;
use Sift\Skills\SkillManagedMetadata;
use Sift\Skills\SkillSource;
use Sift\Skills\Targets\SkillTargetRemoveResult;

it('exposes skill source values and clones with a resolved path', function (): void {
    $source = new SkillSource(
        source: 'owner/repo',
        type: 'github',
        repositoryUrl: 'https://github.com/owner/repo',
        warnings: ['using default branch'],
        resolvedRef: 'main',
    );
    $resolved = $source->withPath('/tmp/repo', 'abc123');

    expect($source->source())->toBe('owner/repo');
    expect($source->type())->toBe('github');
    expect($source->path())->toBeNull();
    expect($source->repositoryUrl())->toBe('https://github.com/owner/repo');
    expect($source->warnings())->toBe(['using default branch']);
    expect($source->resolvedRef())->toBe('main');

    expect($resolved->source())->toBe('owner/repo');
    expect($resolved->type())->toBe('github');
    expect($resolved->path())->toBe('/tmp/repo');
    expect($resolved->repositoryUrl())->toBe('https://github.com/owner/repo');
    expect($resolved->warnings())->toBe(['using default branch']);
    expect($resolved->resolvedRef())->toBe('abc123');
});

it('runs cloned skill source cleanup and exposes the source', function (): void {
    $cleaned = false;
    $source = new SkillSource('owner/repo', 'github', repositoryUrl: 'https://github.com/owner/repo');
    $cloned = new ClonedSkillSource($source, static function () use (&$cleaned): void {
        $cleaned = true;
    });

    expect($cloned->source())->toBe($source);

    $cloned->cleanup();

    expect($cleaned)->toBeTrue();
});

it('hydrates managed skill metadata from payloads', function (): void {
    $metadata = SkillManagedMetadata::fromPayload([
        'name' => 'sift',
        'source' => 'owner/repo',
        'source_type' => 'github',
        'resolved_ref' => 'abc123',
        'installed_at' => '2026-06-04T00:00:00+00:00',
        'targets' => ['codex', 'codex', 'generic'],
    ], 'fallback');

    expect($metadata)->toBeInstanceOf(SkillManagedMetadata::class);
    expect($metadata?->name())->toBe('sift');
    expect($metadata?->source())->toBe('owner/repo');
    expect($metadata?->targets())->toBe(['codex', 'generic']);
    expect($metadata?->toItem())->toBe([
        'name' => 'sift',
        'source' => 'owner/repo',
        'source_type' => 'github',
        'resolved_ref' => 'abc123',
        'installed_at' => '2026-06-04T00:00:00+00:00',
        'targets' => ['codex', 'generic'],
    ]);
});

it('rejects invalid managed skill metadata payloads', function (): void {
    expect(SkillManagedMetadata::fromPayload([
        'name' => 'Invalid Name',
        'source' => 'owner/repo',
        'source_type' => 'github',
        'installed_at' => '2026-06-04T00:00:00+00:00',
        'targets' => ['codex'],
    ], 'fallback'))->toBeNull();

    expect(SkillManagedMetadata::fromPayload([
        'source' => '',
        'source_type' => 'github',
        'installed_at' => '2026-06-04T00:00:00+00:00',
        'targets' => ['codex'],
    ], 'fallback'))->toBeNull();
});

it('serializes skill target remove results to history items', function (): void {
    $result = new SkillTargetRemoveResult('sift', 'codex', '/tmp/sift', 'removed');

    expect($result->toItem())->toBe([
        'name' => 'sift',
        'target' => 'codex',
        'path' => '/tmp/sift',
        'action' => 'removed',
    ]);
});
