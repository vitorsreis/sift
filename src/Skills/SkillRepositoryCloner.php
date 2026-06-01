<?php

declare(strict_types=1);

namespace Sift\Skills;

use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\ProcessRunner;
use Sift\Execution\ToolLocator;
use Sift\Filesystem\Path;
use Throwable;

final readonly class SkillRepositoryCloner
{
    public function __construct(
        private ProcessRunner $processRunner = new ProcessRunner(),
        private ToolLocator $toolLocator = new ToolLocator(),
        private SkillSourcePolicy $policy = new SkillSourcePolicy(),
    ) {}

    public function clone(SkillSource $source, string $cwd): ClonedSkillSource
    {
        $repositoryUrl = $source->repositoryUrl();

        if ($repositoryUrl === null) {
            return new ClonedSkillSource($source, static function (): void {});
        }

        $target = Path::join(sys_get_temp_dir(), 'sift-skill-repo-' . bin2hex(random_bytes(8)));
        $gitBinary = $this->gitBinary($cwd);
        $clone = $this->processRunner->run(new PreparedCommand(
            tool: 'git',
            binary: $gitBinary,
            arguments: ['clone', '--depth=1', $repositoryUrl, $target],
            cwd: $cwd,
            timeout: 120,
        ));

        if (! $clone->successful()) {
            $this->deleteTree($target);

            throw UserFacingException::withContext(
                errorCode: ErrorCode::GithubCloneFailed,
                message: sprintf('Could not clone skill source "%s".', $source->source()),
                context: [
                    'source' => $source->source(),
                    'stderr' => trim($clone->stderr()),
                ],
            );
        }

        try {
            $this->policy->assertNoRequiredSubmodules($source->source(), $target);
            $resolvedRef = $this->resolvedRef($target, $gitBinary);
        } catch (Throwable $throwable) {
            $this->deleteTree($target);

            throw $throwable;
        }

        return new ClonedSkillSource(
            source: $source->withPath($target, $resolvedRef),
            cleanup: function () use ($target): void {
                $this->deleteTree($target);
            },
        );
    }

    private function gitBinary(string $cwd): string
    {
        try {
            return $this->toolLocator->locate('git', 'git', $cwd)->binary();
        } catch (UserFacingException $userFacingException) {
            if ($userFacingException->errorCode() !== ErrorCode::ToolNotFound) {
                throw $userFacingException;
            }

            throw UserFacingException::withContext(
                errorCode: ErrorCode::ToolNotFound,
                message: 'Git is required to install skills from GitHub.',
                hint: 'Install Git or use a local skill source.',
                context: [
                    'tool' => 'git',
                    'source' => 'github_skill_source',
                ],
            );
        }
    }

    private function resolvedRef(string $path, string $gitBinary): ?string
    {
        $result = $this->processRunner->run(new PreparedCommand(
            tool: 'git',
            binary: $gitBinary,
            arguments: ['-C', $path, 'rev-parse', 'HEAD'],
            cwd: $path,
            timeout: 10,
        ));

        if (! $result->successful()) {
            return null;
        }

        $ref = trim($result->stdout());

        return $ref === '' ? null : $ref;
    }

    private function deleteTree(string $path): null
    {
        if (! is_dir($path)) {
            return null;
        }

        $entries = scandir($path);

        if ($entries === false) {
            return null;
        }

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $child = Path::join($path, $entry);

            if (is_dir($child) && ! is_link($child)) {
                $this->deleteTree($child);
                continue;
            }

            @unlink($child);
        }

        @rmdir($path);

        return null;
    }
}
