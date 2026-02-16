<?php

declare(strict_types=1);

use Sift\Console\OptionParser;
use Sift\Exceptions\UserFacingException;

it('parses global runtime options before the command', function (): void {
    $parsed = (new OptionParser)->parse([
        '--raw',
        '--show-process',
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
        'raw' => true,
        'show_process' => true,
        'history' => false,
        'config' => 'custom.sift.json',
        'arguments' => ['src'],
    ]);
});

it('parses short runtime aliases before the command', function (): void {
    $parsed = (new OptionParser)->parse([
        '-r',
        '-p',
        '-s',
        'compact',
        '-f',
        'markdown',
        '-c',
        'custom.sift.json',
        'pint',
        'src',
    ]);

    expect($parsed)->toBe([
        'command' => 'pint',
        'pretty' => true,
        'format' => 'markdown',
        'size' => 'compact',
        'raw' => true,
        'show_process' => null,
        'history' => null,
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

it('parses short command-level aliases for init and view', function (): void {
    $init = (new OptionParser)->parseInit([
        '-F',
        '-c',
        'custom.sift.json',
        '-f',
        'markdown',
        '-s',
        'compact',
        '-P',
    ]);
    $view = (new OptionParser)->parseView([
        'list',
        '-l',
        '25',
        '-o',
        '5',
        '-c',
        'custom.sift.json',
        '-p',
    ]);

    expect($init)->toBe([
        'force' => true,
        'format' => 'markdown',
        'size' => 'compact',
        'pretty' => false,
        'config' => 'custom.sift.json',
    ])
        ->and($view)->toBe([
            'run_id' => null,
            'scope' => 'runs',
            'limit' => 25,
            'offset' => 5,
            'list' => true,
            'clear' => false,
            'format' => null,
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
        'interactive' => false,
        'format' => 'markdown',
        'size' => null,
        'pretty' => true,
        'config' => 'custom.sift.json',
    ]);
});

it('parses interactive add when the tool name is omitted', function (): void {
    $parsed = (new OptionParser)->parseAdd([
        '--config=custom.sift.json',
        '--pretty',
    ]);

    expect($parsed)->toBe([
        'tool' => null,
        'interactive' => true,
        'format' => null,
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

it('parses raw mode for wrapped tools only', function (): void {
    $parsed = (new OptionParser)->parse([
        '--raw',
        '--format=markdown',
        'pint',
        'src',
    ]);

    expect($parsed['raw'])->toBeTrue()
        ->and($parsed['show_process'])->toBeNull()
        ->and($parsed['command'])->toBe('pint')
        ->and($parsed['arguments'])->toBe(['src']);
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

it('rejects short aliases that require a value when none is provided', function (): void {
    try {
        (new OptionParser)->parse(['-f', 'pint']);
        $this->fail('Expected invalid usage exception.');
    } catch (UserFacingException $exception) {
        expect($exception->payload()['error']['code'])->toBe('invalid_usage')
            ->and($exception->payload()['error']['message'])->toBe('Unknown format: pint');
    }

    try {
        (new OptionParser)->parseView(['-l']);
        $this->fail('Expected invalid usage exception.');
    } catch (UserFacingException $exception) {
        expect($exception->payload()['error']['code'])->toBe('invalid_usage')
            ->and($exception->payload()['error']['message'])->toBe('The option `-l` requires a value.');
    }
});

it('rejects add with multiple tool names', function (): void {
    try {
        (new OptionParser)->parseAdd(['phpstan', 'pint']);
        $this->fail('Expected invalid usage exception.');
    } catch (UserFacingException $exception) {
        expect($exception->payload()['error']['code'])->toBe('invalid_usage')
            ->and($exception->payload()['error']['message'])->toBe('The `add` command accepts at most one supported tool name.');
    }
});
