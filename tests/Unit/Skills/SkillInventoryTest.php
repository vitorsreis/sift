<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Skills\ManagedBlockEditor;
use Sift\Skills\SkillInventory;
use Sift\Skills\SkillManagedMetadata;
use Tests\Support\FixtureProject;

it('returns an empty inventory when instruction target file is missing', function (): void {
    $project = FixtureProject::create();

    expect((new SkillInventory())->list($project->root(), ['generic']))->toBe([]);
});

it('reads managed generic skill blocks from real target files', function (): void {
    $project = FixtureProject::create();
    $editor = new ManagedBlockEditor();
    $contents = "Manual mention of php-review\n";
    $contents = $editor->upsert($contents, 'laravel-review', [
        'name' => 'laravel-review',
        'source' => 'repo',
        'source_type' => 'local',
        'installed_at' => '2026-06-01T00:00:00+00:00',
        'targets' => ['generic'],
    ], "Laravel body\n");
    $contents = $editor->upsert($contents, 'php-review', [
        'name' => 'php-review',
        'source' => 'repo',
        'source_type' => 'local',
        'installed_at' => '2026-06-01T00:00:00+00:00',
        'targets' => ['cursor'],
    ], "PHP body\n");
    $project->write('AGENTS.md', $contents);

    $items = (new SkillInventory())->list($project->root(), ['generic']);

    expect(array_map(static fn(SkillManagedMetadata $metadata): string => $metadata->name(), $items))->toBe(['laravel-review']);
});

it('rejects unsupported inventory targets', function (): void {
    $project = FixtureProject::create();

    try {
        (new SkillInventory())->list($project->root(), ['unknown']);
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::UnsupportedTarget);

        return;
    }

    throw new RuntimeException('Expected unsupported target failure.');
});
