<?php

declare(strict_types=1);

use Sift\History\RunIdGenerator;

it('generates time based lowercase run ids that cannot collide with reserved subcommands', function (): void {
    $generator = new RunIdGenerator();
    $id = $generator->generateUniqueId();

    expect($id)->toMatch('/^[0-9a-z]{14}$/');
    expect(['list', 'view', 'remove', 'clear'])->not->toContain($id);
    expect($generator->generateFullId('php cs fixer'))->toMatch('/^sift_[0-9a-z]{14}_php-cs-fixer$/');
});
