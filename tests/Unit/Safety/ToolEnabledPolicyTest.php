<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Safety\ToolEnabledPolicy;
use Sift\Tools\ToolContext;

it('blocks disabled tools before process execution', function (): void {
    $violations = (new ToolEnabledPolicy())->violations(
        command: new PreparedCommand('pest', 'vendor/bin/pest'),
        context: new ToolContext('pest'),
        config: new ToolConfig('pest', false, null, [], 1800),
    );

    expect($violations)->toHaveCount(1);
    expect($violations[0]->code())->toBe(ErrorCode::ToolDisabled);
    expect($violations[0]->policy())->toBe('tool_enabled');
});

it('allows enabled tools', function (): void {
    $violations = (new ToolEnabledPolicy())->violations(
        command: new PreparedCommand('pest', 'vendor/bin/pest'),
        context: new ToolContext('pest'),
        config: new ToolConfig('pest', true, null, [], 1800),
    );

    expect($violations)->toBe([]);
});
