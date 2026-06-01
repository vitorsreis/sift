<?php

declare(strict_types=1);

use Sift\History\RunIdGenerator;

it('generates lowercase cryptographic run ids that cannot collide with reserved subcommands', function (): void {
    $id = (new RunIdGenerator())->generate();

    expect($id)->toMatch('/^run_[a-f0-9]{32}$/');
    expect(['list', 'view', 'remove', 'clear'])->not->toContain($id);
});
