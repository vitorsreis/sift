<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Skills\Skill;
use Sift\Skills\SkillSelector;

it('selects all, comma separated and implicit single skills', function (): void {
    $selector = new SkillSelector();
    $skills = [
        skillSelectorSkill('php-review'),
        skillSelectorSkill('laravel-review'),
    ];

    expect(array_map(static fn(Skill $skill): string => $skill->name(), $selector->select($skills, '*', 'repo')))->toBe([
        'php-review',
        'laravel-review',
    ]);
    expect(array_map(static fn(Skill $skill): string => $skill->name(), $selector->select($skills, 'laravel-review,php-review', 'repo')))->toBe([
        'laravel-review',
        'php-review',
    ]);
    expect($selector->select([skillSelectorSkill('single')], null, 'repo')[0]->name())->toBe('single');
});

it('requires explicit selection for multiple skills', function (): void {
    expect(fn(): array => (new SkillSelector())->select([
        skillSelectorSkill('php-review'),
        skillSelectorSkill('laravel-review'),
    ], null, 'repo'))->toThrow(UserFacingException::class);

    try {
        (new SkillSelector())->select([
            skillSelectorSkill('php-review'),
            skillSelectorSkill('laravel-review'),
        ], null, 'repo');
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::SkillSelectionRequired);
    }
});

function skillSelectorSkill(string $name): Skill
{
    return new Skill(
        name: $name,
        description: 'Use ' . $name . '.',
        path: '/tmp/' . $name,
        skillFile: '/tmp/' . $name . '/SKILL.md',
        source: 'repo',
        sourceType: 'local',
    );
}
