<?php

declare(strict_types=1);

namespace Sift\Skills;

use Sift\Console\InvalidUsageException;
use Sift\Filesystem\Path;

final readonly class SkillDiscovery
{
    public function __construct(
        private SkillMetadataParser $metadataParser = new SkillMetadataParser(),
    ) {}

    /**
     * @return list<Skill>
     */
    public function discover(string $path, string $source, string $sourceType): array
    {
        $root = Path::normalize($path);
        $candidates = $this->candidateDirectories($root);

        if ($candidates === []) {
            return [];
        }

        $skills = [];

        foreach ($candidates as $candidate) {
            $skill = $this->metadataParser->parse(
                skillFile: Path::join($candidate, 'SKILL.md'),
                path: $candidate,
                source: $source,
                sourceType: $sourceType,
            );

            if (isset($skills[$skill->name()])) {
                throw new InvalidUsageException(sprintf('Duplicate skill name "%s" in source "%s".', $skill->name(), $source));
            }

            $skills[$skill->name()] = $skill;
        }

        ksort($skills);

        return array_values($skills);
    }

    /**
     * @return list<string>
     */
    private function candidateDirectories(string $root): array
    {
        $candidates = [];

        if (is_file(Path::join($root, 'SKILL.md'))) {
            $candidates[] = $root;
        }

        foreach (['skills', '.agents/skills'] as $directory) {
            foreach ($this->childDirectories(Path::join($root, $directory)) as $child) {
                if (is_file(Path::join($child, 'SKILL.md'))) {
                    $candidates[] = $child;
                }
            }
        }

        foreach ($this->childDirectories($root) as $child) {
            $name = basename($child);

            if ($name === 'skills') {
                continue;
            }

            if ($name === '.agents') {
                continue;
            }

            if (is_file(Path::join($child, 'SKILL.md'))) {
                $candidates[] = $child;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return list<string>
     */
    private function childDirectories(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $entries = scandir($path);

        if ($entries === false) {
            return [];
        }

        $directories = [];

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $candidate = Path::join($path, $entry);

            if (is_dir($candidate)) {
                $directories[] = $candidate;
            }
        }

        sort($directories);

        return $directories;
    }
}
