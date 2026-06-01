<?php

declare(strict_types=1);

namespace Sift\Skills;

use Sift\Console\InvalidUsageException;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;

final readonly class SkillSelector
{
    /**
     * @param list<Skill> $skills
     *
     * @return list<Skill>
     */
    public function select(array $skills, ?string $selector, string $source): array
    {
        if ($selector === '*') {
            return $skills;
        }

        if ($selector === null || trim($selector) === '') {
            if (count($skills) === 1) {
                return $skills;
            }

            throw UserFacingException::withContext(
                errorCode: ErrorCode::SkillSelectionRequired,
                message: sprintf('Skill source "%s" contains multiple skills. Use --skill, --all, or --list.', $source),
                context: [
                    'source' => $source,
                    'available' => array_map(static fn(Skill $skill): string => $skill->name(), $skills),
                ],
            );
        }

        $indexed = [];

        foreach ($skills as $skill) {
            $indexed[$skill->name()] = $skill;
        }

        $selected = [];

        foreach ($this->names($selector) as $name) {
            $skill = $indexed[$name] ?? null;

            if (! $skill instanceof Skill) {
                throw new InvalidUsageException(sprintf('Skill "%s" was not found in source "%s".', $name, $source));
            }

            $selected[] = $skill;
        }

        return $selected;
    }

    /**
     * @return list<string>
     */
    private function names(string $selector): array
    {
        $names = [];

        foreach (explode(',', $selector) as $name) {
            $name = trim($name);

            if ($name !== '') {
                $names[] = $name;
            }
        }

        if ($names === []) {
            throw new InvalidUsageException('Skill selector cannot be empty.');
        }

        return $names;
    }
}
