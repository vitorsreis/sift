<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\ToolLocator;
use Sift\Filesystem\Path;
use Sift\Skills\SkillRepositoryCloner;
use Sift\Skills\SkillSource;
use Tests\Support\FixtureProject;

it('returns local skill sources without cloning', function (): void {
    $project = FixtureProject::create();
    $source = new SkillSource(
        source: $project->root(),
        type: 'local',
        path: $project->root(),
    );

    $cloned = (new SkillRepositoryCloner())->clone($source, $project->root());

    expect($cloned->source())->toBe($source);

    $cloned->cleanup();

    expect($project->root())->toBeDirectory();
});

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

it('cleans the temporary clone directory when git clone fails', function (): void {
    $project = FixtureProject::create();
    $bin = $project->mkdir('bin');
    writeFailingGit($bin);
    $before = skillCloneDirectories();
    $source = new SkillSource(
        source: 'owner/repo',
        type: 'github',
        repositoryUrl: 'https://github.com/owner/repo.git',
    );
    $cloner = new SkillRepositoryCloner(
        toolLocator: new ToolLocator(pathEnvironment: $bin, pathExtensions: ['.cmd']),
    );

    try {
        $cloner->clone($source, $project->root());
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::GithubCloneFailed);
        expect($userFacingException->context()['stderr'] ?? null)->toContain('clone failed');
        expect(skillCloneDirectories())->toBe($before);

        return;
    }

    throw new RuntimeException('Expected clone failure.');
});

function writeFailingGit(string $bin): void
{
    if (PHP_OS_FAMILY === 'Windows') {
        file_put_contents(Path::join($bin, 'git.cmd'), <<<'BAT'
@echo off
mkdir "%4"
echo cloned > "%4\marker.txt"
echo clone failed 1>&2
exit /b 1
BAT);

        return;
    }

    $path = Path::join($bin, 'git');
    file_put_contents($path, <<<'SH'
#!/bin/sh
mkdir -p "$4"
echo cloned > "$4/marker.txt"
echo clone failed >&2
exit 1
SH);
    chmod($path, 0755);
}

/**
 * @return list<string>
 */
function skillCloneDirectories(): array
{
    $directories = glob(Path::join(sys_get_temp_dir(), 'sift-skill-repo-*')) ?: [];

    sort($directories);

    return $directories;
}
