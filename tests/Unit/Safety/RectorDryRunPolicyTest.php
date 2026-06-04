<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Safety\RectorDryRunPolicy;
use Sift\Tools\ToolContext;

it('requires rector to run in dry-run mode', function (): void {
    $violations = (new RectorDryRunPolicy())->violations(
        command: new PreparedCommand('rector', 'vendor/bin/rector', ['process']),
        context: new ToolContext('rector'),
        config: new ToolConfig('rector', true, null, [], 1800),
    );

    expect($violations)->toHaveCount(1);
    expect($violations[0]->code())->toBe(ErrorCode::PolicyBlocked);
    expect($violations[0]->argument())->toBeNull();
    expect($violations[0]->policy())->toBe('rector_dry_run');
});

it('allows rector with explicit dry-run mode', function (): void {
    $cases = [
        ['--dry-run'],
        ['--dry-run=true'],
        ['--dry-run=1'],
        ['--dry-run', 'true'],
        ['--dry-run', '1'],
    ];

    foreach ($cases as $arguments) {
        $violations = (new RectorDryRunPolicy())->violations(
            command: new PreparedCommand('rector', 'vendor/bin/rector', ['process', ...$arguments]),
            context: new ToolContext('rector'),
            config: new ToolConfig('rector', true, null, [], 1800),
        );

        expect($violations)->toBe([]);
    }
});

it('rejects unsafe dry-run false variants', function (): void {
    $cases = [
        ['--dry-run=false', ['--dry-run=false']],
        ['--dry-run=0', ['--dry-run=0']],
        ['--dry-run', ['--dry-run', 'false']],
        ['--dry-run', ['--dry-run', '0']],
        ['--dry-run=maybe', ['--dry-run=maybe']],
        ['--no-dry-run', ['--no-dry-run']],
    ];

    foreach ($cases as [$expectedArgument, $arguments]) {
        $violations = (new RectorDryRunPolicy())->violations(
            command: new PreparedCommand('rector', 'vendor/bin/rector', ['process', ...$arguments]),
            context: new ToolContext('rector', raw: true),
            config: new ToolConfig('rector', true, null, [], 1800),
        );

        expect($violations)->toHaveCount(1);
        expect($violations[0]->code())->toBe(ErrorCode::PolicyBlocked);
        expect($violations[0]->argument())->toBe($expectedArgument);
        expect($violations[0]->policy())->toBe('rector_dry_run');
    }
});

it('ignores non-rector tools', function (): void {
    $violations = (new RectorDryRunPolicy())->violations(
        command: new PreparedCommand('pest', 'vendor/bin/pest'),
        context: new ToolContext('pest'),
        config: new ToolConfig('pest', true, null, [], 1800),
    );

    expect($violations)->toBe([]);
});
