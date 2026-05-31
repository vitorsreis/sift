<?php

declare(strict_types=1);

use Sift\Console\OutputPreferences;
use Sift\Console\OutputSize;
use Sift\Tools\ToolContext;

it('carries normalized execution context for adapters', function (): void {
    $preferences = new OutputPreferences(
        size: OutputSize::Full,
        pretty: false,
        showProcess: false,
        debug: true,
    );

    $context = new ToolContext(
        toolName: 'pest',
        subcommand: 'test',
        userArgs: ['--filter', 'CheckoutTest'],
        cwd: '/project',
        outputPreferences: $preferences,
        raw: false,
        debug: true,
        repair: false,
        dryRun: true,
        filter: 'CheckoutTest',
        coverage: true,
        coverageMin: 80.5,
        mode: 'test',
        warnings: ['JUnit output was injected.'],
    );

    expect($context->toolName())->toBe('pest');
    expect($context->subcommand())->toBe('test');
    expect($context->userArgs())->toBe(['--filter', 'CheckoutTest']);
    expect($context->cwd())->toBe('/project');
    expect($context->config())->toBeNull();
    expect($context->outputPreferences())->toBe($preferences);
    expect($context->raw())->toBeFalse();
    expect($context->debug())->toBeTrue();
    expect($context->repair())->toBeFalse();
    expect($context->dryRun())->toBeTrue();
    expect($context->filter())->toBe('CheckoutTest');
    expect($context->coverage())->toBeTrue();
    expect($context->coverageMin())->toBe(80.5);
    expect($context->mode())->toBe('test');
    expect($context->warnings())->toBe(['JUnit output was injected.']);
});

it('rejects empty tool names', function (): void {
    expect(fn(): mixed => new ToolContext(toolName: '', cwd: '/project'))
        ->toThrow(InvalidArgumentException::class, 'Tool context name cannot be empty.');
});
