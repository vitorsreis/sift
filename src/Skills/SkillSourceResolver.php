<?php

declare(strict_types=1);

namespace Sift\Skills;

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\Path;

final readonly class SkillSourceResolver
{
    public function __construct(
        private SkillSourcePolicy $policy = new SkillSourcePolicy(),
    ) {}

    public function resolve(string $source, string $cwd): SkillSource
    {
        $source = trim($source);
        $this->policy->assertAllowed($source);

        if ($source === 'sift') {
            return new SkillSource(
                source: $source,
                type: 'bundled',
                path: Path::join(dirname(__DIR__, 2), 'skills/sift'),
            );
        }

        $localPath = $this->localPath($source, $cwd);
        $this->policy->assertLocalPathAllowed($source, $localPath, $cwd);

        if (is_file($localPath) && basename($localPath) === 'SKILL.md') {
            $directory = Path::normalize(dirname($localPath));
            $this->policy->assertNoRequiredSubmodules($source, $directory);

            return new SkillSource($source, 'local', $directory, warnings: ['local_source']);
        }

        if (is_dir($localPath)) {
            $directory = Path::normalize($localPath);
            $this->policy->assertNoRequiredSubmodules($source, $directory);

            return new SkillSource($source, 'local', $directory, warnings: ['local_source']);
        }

        $repository = $this->repository($source);

        if ($repository !== null) {
            return new SkillSource(
                source: $source,
                type: 'github',
                repositoryUrl: $repository['url'],
                warnings: ['unpinned_source'],
                requestedSkill: $repository['requested_skill'],
            );
        }

        throw UserFacingException::withContext(
            errorCode: ErrorCode::SkillSourceNotFound,
            message: sprintf('Skill source "%s" was not found.', $source),
            context: ['source' => $source],
        );
    }

    private function localPath(string $source, string $cwd): string
    {
        if (Path::isAbsolute($source)) {
            return Path::normalize($source);
        }

        return Path::join($cwd, $source);
    }

    /**
     * @return null|array{url: string, requested_skill: string|null}
     */
    private function repository(string $source): ?array
    {
        if (preg_match('#^https://github\.com/(?P<owner>[A-Za-z0-9_.-]+)/(?P<repo>[A-Za-z0-9_.-]+?)(?:\.git)?/?$#', $source, $matches) === 1) {
            return [
                'url' => sprintf('https://github.com/%s/%s.git', $matches['owner'], $matches['repo']),
                'requested_skill' => null,
            ];
        }

        if (preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $source) === 1) {
            return [
                'url' => 'https://github.com/' . $source . '.git',
                'requested_skill' => null,
            ];
        }

        if (preg_match('#^(?P<source>[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+)@(?P<skill>[a-z0-9][a-z0-9-]{0,63})$#', $source, $matches) === 1) {
            return [
                'url' => 'https://github.com/' . $matches['source'] . '.git',
                'requested_skill' => $matches['skill'],
            ];
        }

        return null;
    }
}
