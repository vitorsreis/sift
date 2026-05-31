<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Safety\BlockedArgumentsPolicy;
use Sift\Tools\ToolContext;

it('blocks configured arguments using the prepared command', function (): void {
    $violations = (new BlockedArgumentsPolicy())->violations(
        command: new PreparedCommand('pint', 'vendor/bin/pint', ['--test', '--config=pint.json', '--fix']),
        context: new ToolContext('pint'),
        config: new ToolConfig('pint', true, null, ['--fix'], 1800),
    );

    expect($violations)->toHaveCount(1);
    expect($violations[0]->code())->toBe(ErrorCode::BlockedArgument);
    expect($violations[0]->argument())->toBe('--fix');
    expect($violations[0]->policy())->toBe('blocked_arguments');
});

it('matches blocked options passed with inline values', function (): void {
    $violations = (new BlockedArgumentsPolicy())->violations(
        command: new PreparedCommand('tool', 'tool', ['--format=json']),
        context: new ToolContext('tool'),
        config: new ToolConfig('tool', true, null, ['--format'], 1800),
    );

    expect($violations)->toHaveCount(1);
    expect($violations[0]->argument())->toBe('--format=json');
});

it('allows commands without blocked arguments', function (): void {
    $violations = (new BlockedArgumentsPolicy())->violations(
        command: new PreparedCommand('pint', 'vendor/bin/pint', ['--test']),
        context: new ToolContext('pint'),
        config: new ToolConfig('pint', true, null, ['--fix'], 1800),
    );

    expect($violations)->toBe([]);
});
