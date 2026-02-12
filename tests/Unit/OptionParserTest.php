<?php

declare(strict_types=1);

use Sift\Console\OptionParser;
use Sift\Exceptions\UserFacingException;

it('parses global runtime options before the command', function (): void {
    $parsed = (new OptionParser)->parse([
        '--pretty',
        '--size=fuller',
        '--no-history',
        '--config=custom.sift.json',
        'pint',
        'src',
    ]);

    expect($parsed)->toBe([
        'command' => 'pint',
        'pretty' => true,
        'format' => null,
        'size' => 'fuller',
        'history' => false,
        'config' => 'custom.sift.json',
        'arguments' => ['src'],
    ]);
});

it('parses command-level options for init', function (): void {
    $parsed = (new OptionParser)->parseInit([
        '--force',
        '--config=custom.sift.json',
        '--format=markdown',
        '--pretty',
    ]);

    expect($parsed)->toBe([
        'force' => true,
        'format' => 'markdown',
        'size' => null,
        'pretty' => true,
        'config' => 'custom.sift.json',
    ]);
});

it('parses command-level options for add', function (): void {
    $parsed = (new OptionParser)->parseAdd([
        'phpstan',
        '--config=custom.sift.json',
        '--format=markdown',
        '--pretty',
    ]);

    expect($parsed)->toBe([
        'tool' => 'phpstan',
        'format' => 'markdown',
        'size' => null,
        'pretty' => true,
        'config' => 'custom.sift.json',
    ]);
});

it('parses command-level options for view clear', function (): void {
    $parsed = (new OptionParser)->parseView([
        '--clear',
        '--config=custom.sift.json',
        '--pretty',
    ]);

    expect($parsed)->toBe([
        'run_id' => null,
        'scope' => 'clear',
        'limit' => 10,
        'offset' => 0,
        'list' => false,
        'clear' => true,
        'format' => null,
        'size' => null,
        'pretty' => true,
        'config' => 'custom.sift.json',
    ]);
});

it('rejects invalid positional arguments for view clear', function (): void {
    try {
        (new OptionParser)->parseView(['--clear', 'abc']);
        $this->fail('Expected invalid usage exception.');
    } catch (UserFacingException $exception) {
        expect($exception->payload()['error']['code'])->toBe('invalid_usage')
            ->and($exception->payload()['error']['message'])->toContain('does not accept a run id or scope');
    }
});

it('rejects unknown validate options', function (): void {
    try {
        (new OptionParser)->parseValidate(['--bogus']);
        $this->fail('Expected invalid usage exception.');
    } catch (UserFacingException $exception) {
        expect($exception->payload()['error']['code'])->toBe('invalid_usage')
            ->and($exception->payload()['error']['message'])->toBe('Unknown validate option: --bogus');
    }
});

it('rejects add without a tool name', function (): void {
    try {
        (new OptionParser)->parseAdd(['--pretty']);
        $this->fail('Expected invalid usage exception.');
    } catch (UserFacingException $exception) {
        expect($exception->payload()['error']['code'])->toBe('invalid_usage')
            ->and($exception->payload()['error']['message'])->toBe('The `add` command requires a supported tool name.');
    }
});
