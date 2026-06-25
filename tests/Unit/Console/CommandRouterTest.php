<?php

declare(strict_types=1);

use Sift\Console\CliParser;
use Sift\Console\CommandRouter;
use Sift\Console\InvalidUsageException;

/**
 * @param list<string> $tokens
 */
function expectSiftRoute(array $tokens, string $handler): void
{
    $request = CliParser::forSift()->parse($tokens);
    $route = CommandRouter::forSift()->route($request);

    expect($route->handler())->toBe($handler);
}

it('routes top-level builtins and aliases', function (): void {
    expectSiftRoute(['help'], 'help');
    expectSiftRoute(['--help'], 'help');
    expectSiftRoute(['-h'], 'help');
    expectSiftRoute(['version'], 'version');
    expectSiftRoute(['--version'], 'version');
    expectSiftRoute(['-V'], 'version');
    expectSiftRoute(['init'], 'init');
    expectSiftRoute(['validate'], 'validate');
});

it('routes nested commands and aliases', function (): void {
    expectSiftRoute(['skills'], 'skills.help');
    expectSiftRoute(['skills', 'list'], 'skills.list');
    expectSiftRoute(['skills', 'ls'], 'skills.list');
    expectSiftRoute(['skills', 'add', 'vitorsreis/sift'], 'skills.add');
    expectSiftRoute(['skills', 'find', 'review'], 'skills.find');
    expectSiftRoute(['skills', 'init', 'review'], 'skills.init');
    expectSiftRoute(['skills', 'remove', 'review'], 'skills.remove');
    expectSiftRoute(['skills', 'rm', 'review'], 'skills.remove');
    expectSiftRoute(['skills', 'update', 'review'], 'skills.update');
    expectSiftRoute(['tools', 'list'], 'tools.list');
    expectSiftRoute(['tools', 'ls'], 'tools.list');
    expectSiftRoute(['history', 'list'], 'history.list');
    expectSiftRoute(['history', 'ls'], 'history.list');
    expectSiftRoute(['history', 'view', '0td7j1a01z141z'], 'history.view');
    expectSiftRoute(['history', '0td7j1a01z141z'], 'history.view');
    expectSiftRoute(['history', 'view', 'sift_0td7j1a01z141z_pest'], 'history.view');
    expectSiftRoute(['history', 'view', '0td7j1a01z141z', 'items'], 'history.view');
    expectSiftRoute(['history', 'clear'], 'history.clear');
    expectSiftRoute(['history', 'remove', '0td7j1a01z141z'], 'history.remove');
    expectSiftRoute(['history', 'rm', '0td7j1a01z141z'], 'history.remove');
});

it('routes direct tool aliases and explicit run commands', function (): void {
    $router = CommandRouter::forSift();

    $direct = $router->route(CliParser::forSift()->parse(['pest', '--filter=CheckoutTest']));
    $explicit = $router->route(CliParser::forSift()->parse(['run', 'help', '--version']));

    expect($direct->handler())->toBe('run.tool');
    expect($direct->arguments())->toBe(['pest', '--filter=CheckoutTest']);
    expect($explicit->handler())->toBe('run.tool');
    expect($explicit->arguments())->toBe(['help', '--version']);
});

it('does not expose unsupported top-level subcommands', function (): void {
    expect(fn(): mixed => CliParser::forSift()->parse(['tools', 'add']))
        ->toThrow(InvalidUsageException::class, 'Unknown command "tools add".');

    expect(fn(): mixed => CliParser::forSift()->parse(['add']))
        ->toThrow(InvalidUsageException::class, 'Unknown command "add".');

    expect(fn(): mixed => CliParser::forSift()->parse(['list']))
        ->toThrow(InvalidUsageException::class, 'Unknown command "list".');

    expect(fn(): mixed => CliParser::forSift()->parse(['view']))
        ->toThrow(InvalidUsageException::class, 'Unknown command "view".');

    expect(fn(): mixed => CliParser::forSift()->parse(['runs']))
        ->toThrow(InvalidUsageException::class, 'Unknown command "runs".');
});
