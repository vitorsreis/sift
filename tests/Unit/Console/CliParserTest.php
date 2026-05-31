<?php

declare(strict_types=1);

use Sift\Console\CliParser;
use Sift\Console\InvalidUsageException;

/**
 * @param list<string> $tokens
 * @param array<string, bool|int|string> $expectedOptions
 * @param list<string> $expectedArguments
 */
function expectParsedCommandOptions(array $tokens, string $command, array $expectedOptions, array $expectedArguments = []): void
{
    $request = CliParser::forSift()->parse($tokens);

    expect($request->command())->toBe($command);
    expect($request->arguments())->toBe($expectedArguments);
    expect($request->options())->toBe($expectedOptions);
}

it('parses global options before the command', function (): void {
    $request = CliParser::forSift()->parse([
        '--compact',
        '--full',
        '--pretty',
        '--raw',
        '--show-process',
        '--no-show-process',
        '--debug',
        '--history',
        '--no-history',
        '--config=sift.json',
        'validate',
    ]);

    expect($request->command())->toBe('validate');
    expect($request->globalOptions())->toBe([
        'compact' => true,
        'full' => true,
        'pretty' => true,
        'raw' => true,
        'show-process' => true,
        'no-show-process' => true,
        'debug' => true,
        'history' => true,
        'no-history' => true,
        'config' => 'sift.json',
    ]);
    expect($request->options())->toBe([]);
    expect($request->arguments())->toBe([]);
});

it('parses global short aliases before the command', function (): void {
    $request = CliParser::forSift()->parse(['-p', '-P', '-c', 'sift.json', 'validate']);

    expect($request->command())->toBe('validate');
    expect($request->globalOptions())->toBe([
        'pretty' => true,
        'no-pretty' => true,
        'config' => 'sift.json',
    ]);
});

it('does not consume the next token as a boolean flag value', function (): void {
    $request = CliParser::forSift()->parse(['--compact', 'pest', '--filter=CheckoutTest']);

    expect($request->command())->toBe('run');
    expect($request->globalOptions())->toBe(['compact' => true]);
    expect($request->arguments())->toBe(['pest', '--filter=CheckoutTest']);
});

it('parses command options after a declared command', function (): void {
    $request = CliParser::forSift()->parse([
        'skills',
        'add',
        'vitorsreis/sift',
        '--skill',
        'sift',
        '--agent=codex',
        '--yes',
    ]);

    expect($request->command())->toBe('skills add');
    expect($request->arguments())->toBe(['vitorsreis/sift']);
    expect($request->options())->toBe([
        'skill' => 'sift',
        'agent' => 'codex',
        'yes' => true,
    ]);
});

it('parses every declared command option set', function (): void {
    expectParsedCommandOptions(['init', '-f', '-y', '--skill', '--no-skill', '-c', 'sift.json'], 'init', [
        'force' => true,
        'yes' => true,
        'skill' => true,
        'no-skill' => true,
        'config' => 'sift.json',
    ]);

    expectParsedCommandOptions(['validate', '--config=sift.json'], 'validate', ['config' => 'sift.json']);
    expectParsedCommandOptions(['tools', 'list', '-c', 'sift.json'], 'tools list', ['config' => 'sift.json']);
    expectParsedCommandOptions(['skills', 'add', 'vitorsreis/sift', '-g', '-a', 'codex', '-s', 'sift', '-l', '-y', '--all', '-c', 'sift.json'], 'skills add', [
        'global' => true,
        'agent' => 'codex',
        'skill' => 'sift',
        'list' => true,
        'yes' => true,
        'all' => true,
        'config' => 'sift.json',
    ], ['vitorsreis/sift']);
    expectParsedCommandOptions(['skills', 'list', '-g', '-a', 'codex', '-s', 'sift'], 'skills list', [
        'global' => true,
        'agent' => 'codex',
        'skill' => 'sift',
    ]);
    expectParsedCommandOptions(['skills', 'remove', 'sift', '-g', '-a', 'codex', '-s', 'sift', '-y', '--all'], 'skills remove', [
        'global' => true,
        'agent' => 'codex',
        'skill' => 'sift',
        'yes' => true,
        'all' => true,
    ], ['sift']);
    expectParsedCommandOptions(['skills', 'update', 'sift', '-g', '-a', 'codex', '-s', 'sift', '-y', '--all'], 'skills update', [
        'global' => true,
        'agent' => 'codex',
        'skill' => 'sift',
        'yes' => true,
        'all' => true,
    ], ['sift']);
    expectParsedCommandOptions(['skills', 'init', 'review', '-y'], 'skills init', ['yes' => true], ['review']);
    expectParsedCommandOptions(['history', 'list', '-l', '-1', '--offset=-2', '-c', 'sift.json'], 'history list', [
        'limit' => -1,
        'offset' => -2,
        'config' => 'sift.json',
    ]);
});

it('preserves option-like tokens after a direct tool name', function (): void {
    $request = CliParser::forSift()->parse(['pest', '--config', 'phpunit.xml', '--compact']);

    expect($request->command())->toBe('run');
    expect($request->arguments())->toBe(['pest', '--config', 'phpunit.xml', '--compact']);
    expect($request->globalOptions())->toBe([]);
});

it('preserves tokens after the end-of-options marker', function (): void {
    $request = CliParser::forSift()->parse(['--compact', 'pest', '--', '--filter=CheckoutTest']);

    expect($request->command())->toBe('run');
    expect($request->globalOptions())->toBe(['compact' => true]);
    expect($request->arguments())->toBe(['pest', '--filter=CheckoutTest']);
});

it('treats config after a declared command as a command option', function (): void {
    $request = CliParser::forSift()->parse(['validate', '--config', 'sift.json']);

    expect($request->command())->toBe('validate');
    expect($request->options())->toBe(['config' => 'sift.json']);
    expect($request->arguments())->toBe([]);
});

it('rejects boolean flags with inline values', function (): void {
    expect(fn(): mixed => CliParser::forSift()->parse(['--compact=true', 'help']))
        ->toThrow(InvalidUsageException::class, 'Option "--compact" does not accept a value.');
});

it('rejects missing option values', function (): void {
    expect(fn(): mixed => CliParser::forSift()->parse(['history', 'list', '--limit', '--offset=1']))
        ->toThrow(InvalidUsageException::class, 'Option "--limit" requires a value.');

    expect(fn(): mixed => CliParser::forSift()->parse(['history', 'list', '--offset', '--limit=1']))
        ->toThrow(InvalidUsageException::class, 'Option "--offset" requires a value.');
});

it('rejects invalid integer option values', function (string $value): void {
    expect(fn(): mixed => CliParser::forSift()->parse(['history', 'list', '--limit=' . $value]))
        ->toThrow(InvalidUsageException::class, 'Option "--limit" expects an integer.');

    expect(fn(): mixed => CliParser::forSift()->parse(['history', 'list', '--offset=' . $value]))
        ->toThrow(InvalidUsageException::class, 'Option "--offset" expects an integer.');
})->with(['abc', '1.2', '']);

it('rejects duplicated non-repeatable options', function (): void {
    expect(fn(): mixed => CliParser::forSift()->parse(['--compact', '--compact', 'help']))
        ->toThrow(InvalidUsageException::class, 'Option "--compact" cannot be repeated.');
});

it('does not group short aliases', function (): void {
    expect(fn(): mixed => CliParser::forSift()->parse(['skills', 'add', 'vitorsreis/sift', '-abc']))
        ->toThrow(InvalidUsageException::class, 'Unknown option "-abc".');
});

it('rejects config after commands that do not declare it', function (): void {
    expect(fn(): mixed => CliParser::forSift()->parse(['skills', 'find', 'review', '--config=sift.json']))
        ->toThrow(InvalidUsageException::class, 'Unknown option "--config".');
});
