<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Safety\ComposerReadOnlyPolicy;
use Sift\Tools\ToolContext;

it('allows read-only composer subcommands', function (string $subcommand): void {
    $violations = (new ComposerReadOnlyPolicy())->violations(
        command: new PreparedCommand('composer', 'composer', [$subcommand]),
        context: new ToolContext('composer'),
        config: new ToolConfig('composer', true, null, [], 1800),
    );

    expect($violations)->toBe([]);
})->with(['audit', 'licenses', 'outdated', 'show']);

it('blocks mutating composer subcommands before process execution', function (string $subcommand): void {
    $violations = (new ComposerReadOnlyPolicy())->violations(
        command: new PreparedCommand('composer', 'composer', [$subcommand, 'vendor/package']),
        context: new ToolContext('composer', raw: true),
        config: new ToolConfig('composer', true, null, [], 1800),
    );

    expect($violations)->toHaveCount(1);
    expect($violations[0]->code())->toBe(ErrorCode::PolicyBlocked);
    expect($violations[0]->argument())->toBe($subcommand);
    expect($violations[0]->policy())->toBe('composer_read_only');
})->with(['install', 'update', 'require', 'remove', 'config']);

it('ignores non-composer tools', function (): void {
    $violations = (new ComposerReadOnlyPolicy())->violations(
        command: new PreparedCommand('pest', 'vendor/bin/pest', ['--filter', 'CheckoutTest']),
        context: new ToolContext('pest'),
        config: new ToolConfig('pest', true, null, [], 1800),
    );

    expect($violations)->toBe([]);
});

it('blocks composer without an explicit read-only subcommand', function (): void {
    $violations = (new ComposerReadOnlyPolicy())->violations(
        command: new PreparedCommand('composer', 'composer'),
        context: new ToolContext('composer'),
        config: new ToolConfig('composer', true, null, [], 1800),
    );

    expect($violations)->toHaveCount(1);
    expect($violations[0]->argument())->toBeNull();
});
