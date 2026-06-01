<?php

declare(strict_types=1);

use Sift\Skills\SkillMetadataParser;
use Tests\Support\FixtureProject;

it('parses simple skill frontmatter', function (): void {
    $project = FixtureProject::create();
    $path = $project->write('SKILL.md', <<<'MD'
---
name: php-review
description: Use when reviewing PHP code.
---

# PHP Review
MD);

    $skill = (new SkillMetadataParser())->parse($path, $project->root(), 'local-source', 'local');

    expect($skill->name())->toBe('php-review');
    expect($skill->description())->toBe('Use when reviewing PHP code.');
    expect($skill->skillFile())->toBe($path);
});

it('parses folded description frontmatter', function (): void {
    $project = FixtureProject::create();
    $path = $project->write('SKILL.md', <<<'MD'
---
name: php-review
description: >
  Use when reviewing PHP code,
  tests, and agent workflows.
---

# PHP Review
MD);

    $skill = (new SkillMetadataParser())->parse($path, $project->root(), 'local-source', 'local');

    expect($skill->description())->toBe('Use when reviewing PHP code, tests, and agent workflows.');
});
