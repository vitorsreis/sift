<?php

declare(strict_types=1);

use Sift\Skills\SkillManagedMetadata;
use Sift\Skills\SkillService;
use Tests\Support\FixtureProject;

it('caches inventory lookups during the service lifetime', function (): void {
    $calls = 0;
    $project = FixtureProject::create();
    $metadata = skillServiceMetadata('php-review');
    $service = new SkillService(inventoryResolver: function () use (&$calls, $metadata): array {
        ++$calls;

        return [$metadata];
    });

    $first = $service->inventory($project->root(), ['generic']);
    $second = $service->inventory($project->root(), ['generic']);
    $third = $service->inventory($project->root(), ['cursor']);

    expect($first)->toBe($second);
    expect($third)->toBe([$metadata]);
    expect($calls)->toBe(2);
});

it('selects managed skills by name', function (): void {
    $service = new SkillService();
    $php = skillServiceMetadata('php-review');
    $laravel = skillServiceMetadata('laravel-review');

    expect($service->selectByName([$php, $laravel], ['php-review']))->toBe([$php]);
    expect($service->selectByName([$php, $laravel], ['*']))->toBe([$php, $laravel]);
    expect($service->selectByName([$php, $laravel], []))->toBe([$php, $laravel]);
});

function skillServiceMetadata(string $name): SkillManagedMetadata
{
    return new SkillManagedMetadata(
        name: $name,
        source: 'vendor/source',
        sourceType: 'local',
        resolvedRef: null,
        installedAt: '2026-06-01T00:00:00+00:00',
        targets: ['generic'],
    );
}
