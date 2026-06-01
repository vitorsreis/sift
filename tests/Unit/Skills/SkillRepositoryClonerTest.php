<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\ToolLocator;
use Sift\Skills\SkillRepositoryCloner;
use Sift\Skills\SkillSource;
use Tests\Support\FixtureProject;

it('returns a clear error when git is not installed for github skill sources', function (): void {
    $project = FixtureProject::create();
    $source = new SkillSource(
        source: 'owner/repo',
        type: 'github',
        repositoryUrl: 'https://github.com/owner/repo.git',
    );
    $cloner = new SkillRepositoryCloner(
        toolLocator: new ToolLocator(pathEnvironment: '', pathExtensions: []),
    );

    try {
        $cloner->clone($source, $project->root());
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::ToolNotFound);
        expect($userFacingException->getMessage())->toBe('Git is required to install skills from GitHub.');
        expect($userFacingException->hint())->toBe('Install Git or use a local skill source.');

        return;
    }

    throw new RuntimeException('Expected missing git to fail before clone.');
});
