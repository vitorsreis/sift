<?php

declare(strict_types=1);

use Sift\Filesystem\Path;

it('preserves stream wrapper paths when normalizing and joining', function (): void {
    $base = 'phar://D:/Work/projects/others/sift/build/phar/sift.phar';

    expect(Path::normalize($base . '/src/../skills'))->toBe($base . '/src/../skills');
    expect(Path::join($base, 'skills/sift', 'SKILL.md'))->toBe($base . '/skills/sift/SKILL.md');
});
