<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Skills\SkillTargetLock;
use Tests\Support\FixtureProject;

it('holds the target lock while running the callback', function (): void {
    $project = FixtureProject::create();
    $observedHeldLock = false;

    $result = (new SkillTargetLock())->synchronized($project->root(), ['generic'], function () use ($project, &$observedHeldLock): string {
        $handle = fopen($project->path('.sift/locks/skills/generic.lock'), 'c+b');

        if (! is_resource($handle)) {
            return 'failed';
        }

        $observedHeldLock = flock($handle, LOCK_EX | LOCK_NB) === false;
        fclose($handle);

        return 'ok';
    });

    expect($result)->toBe('ok');
    expect($observedHeldLock)->toBeTrue();
});

it('returns a filesystem error when the target lock is already held', function (): void {
    $project = FixtureProject::create();
    $lockPath = $project->write('.sift/locks/skills/generic.lock', '');
    $handle = fopen($lockPath, 'c+b');

    if (! is_resource($handle)) {
        throw new RuntimeException('Could not open lock fixture.');
    }

    flock($handle, LOCK_EX);

    try {
        (new SkillTargetLock())->synchronized($project->root(), ['generic'], static fn(): string => 'never');
    } catch (UserFacingException $userFacingException) {
        flock($handle, LOCK_UN);
        fclose($handle);

        expect($userFacingException->errorCode())->toBe(ErrorCode::FilesystemError);

        return;
    }

    flock($handle, LOCK_UN);
    fclose($handle);

    throw new RuntimeException('Expected lock failure.');
});
