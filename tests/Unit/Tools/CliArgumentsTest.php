<?php

declare(strict_types=1);

use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;
use Sift\Tools\CliArguments;

it('is built from the normalized command route', function (): void {
    $arguments = CliArguments::fromRoute(new CommandRoute(
        handler: 'run',
        arguments: ['pest', '--filter', 'CheckoutTest', '--coverage'],
        globalOptions: ['compact' => true],
    ));

    expect($arguments->tool())->toBe('pest');
    expect($arguments->toolArguments())->toBe(['--filter', 'CheckoutTest', '--coverage']);
    expect($arguments->siftOption('compact'))->toBeTrue();
});

it('detects flags and any matching flag', function (): void {
    $arguments = new CliArguments(
        tool: 'phpstan',
        toolArguments: ['analyse', '--error-format=json', '--no-progress'],
    );

    expect($arguments->has('analyse'))->toBeTrue();
    expect($arguments->has('--error-format'))->toBeTrue();
    expect($arguments->has('--missing'))->toBeFalse();
    expect($arguments->hasAny(['--missing', '--no-progress']))->toBeTrue();
});

it('reads inline and separated argument values', function (): void {
    $arguments = new CliArguments(
        tool: 'pest',
        toolArguments: ['--filter', 'CheckoutTest', '--min=80.5'],
    );

    expect($arguments->value('--filter'))->toBe('CheckoutTest');
    expect($arguments->value('--min'))->toBe('80.5');
    expect($arguments->value('--coverage'))->toBeNull();
});

it('does not treat another flag as a value', function (): void {
    $arguments = new CliArguments(
        tool: 'rector',
        toolArguments: ['--dry-run', '--output-format=json'],
    );

    expect($arguments->has('--dry-run'))->toBeTrue();
    expect($arguments->value('--dry-run'))->toBeNull();
    expect($arguments->value('--output-format'))->toBe('json');
});

it('reads required and float values safely', function (): void {
    $arguments = new CliArguments(
        tool: 'pest',
        toolArguments: ['--coverage-min=82.5'],
    );

    expect($arguments->requiredValue('--coverage-min'))->toBe('82.5');
    expect($arguments->floatValue('--coverage-min'))->toBe(82.5);

    expect(fn(): mixed => $arguments->requiredValue('--missing'))
        ->toThrow(InvalidUsageException::class, 'Argument "--missing" requires a value.');

    expect(fn(): mixed => (new CliArguments('pest', ['--coverage-min=abc']))->floatValue('--coverage-min'))
        ->toThrow(InvalidUsageException::class, 'Argument "--coverage-min" expects a numeric value.');
});
