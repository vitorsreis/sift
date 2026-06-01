<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Skills\SkillSourcePolicy;

it('rejects unsafe skill sources before clone or copy', function (string $source): void {
    try {
        (new SkillSourcePolicy())->assertAllowed($source);
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::PolicyBlocked);

        return;
    }

    throw new RuntimeException('Expected source policy to reject source.');
})->with([
    'http://github.com/owner/repo',
    'ssh://github.com/owner/repo',
    'git://github.com/owner/repo',
    'git@github.com:owner/repo.git',
    'https://token@github.com/owner/repo',
    '../repo',
    'skills/../repo',
]);

it('allows bundled local and github skill sources', function (string $source): void {
    (new SkillSourcePolicy())->assertAllowed($source);

    expect(true)->toBeTrue();
})->with([
    'sift',
    'skills/sift',
    'owner/repo',
    'https://github.com/owner/repo',
]);
