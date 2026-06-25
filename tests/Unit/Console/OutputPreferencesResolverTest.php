<?php

declare(strict_types=1);

use Sift\Config\HistoryConfig;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;
use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;
use Sift\Console\OutputFormat;
use Sift\Console\OutputPreferencesResolver;
use Sift\Console\OutputSize;

function outputResolverConfig(string $size, bool $pretty, bool $showProcess, string $format = 'terminal', bool $colored = true): SiftConfig
{
    return new SiftConfig(
        schema: 'https://example.com/schema.json',
        configPath: null,
        usingDefaults: false,
        history: new HistoryConfig(
            enabled: true,
            path: '.sift/history',
            maxFiles: 50,
            maxAgeDays: 30,
            maxBytesPerRun: 1048576,
            redactSecrets: true,
        ),
        output: new OutputConfig($size, $pretty, $showProcess, $format, $colored),
        tools: [],
    );
}

it('resolves command options before global options and config', function (): void {
    $route = new CommandRoute(
        handler: 'validate',
        options: [
            'full' => true,
            'pretty' => true,
            'show-process' => true,
        ],
        globalOptions: [
            'compact' => true,
            'no-pretty' => true,
            'no-show-process' => true,
        ],
    );

    $preferences = (new OutputPreferencesResolver())->resolve(
        route: $route,
        config: outputResolverConfig('normal', false, false),
    );

    expect($preferences->size())->toBe(OutputSize::Full);
    expect($preferences->format())->toBe(OutputFormat::Terminal);
    expect($preferences->pretty())->toBeTrue();
    expect($preferences->showProcess())->toBeTrue();
});

it('resolves global options before config and defaults', function (): void {
    $route = new CommandRoute(
        handler: 'validate',
        globalOptions: [
            'compact' => true,
            'no-pretty' => true,
            'no-show-process' => true,
            'debug' => true,
        ],
    );

    $preferences = (new OutputPreferencesResolver())->resolve(
        route: $route,
        config: outputResolverConfig('full', true, true),
    );

    expect($preferences->size())->toBe(OutputSize::Compact);
    expect($preferences->format())->toBe(OutputFormat::Terminal);
    expect($preferences->pretty())->toBeFalse();
    expect($preferences->showProcess())->toBeFalse();
    expect($preferences->debug())->toBeTrue();
});

it('can disable terminal colors with no-color', function (): void {
    $preferences = (new OutputPreferencesResolver())->resolve(
        new CommandRoute('validate', globalOptions: ['no-color' => true]),
    );

    expect($preferences->color())->toBeFalse();
});

it('resolves command-scoped terminal color options', function (): void {
    $preferences = (new OutputPreferencesResolver())->resolve(
        new CommandRoute('validate', options: ['no-color' => true]),
    );

    expect($preferences->color())->toBeFalse();
});

it('uses colored output preference from config', function (): void {
    $preferences = (new OutputPreferencesResolver())->resolve(
        route: new CommandRoute('validate'),
        config: outputResolverConfig('compact', true, false, colored: false),
    );

    expect($preferences->color())->toBeFalse();
});

it('uses terminal output by default and json when requested', function (): void {
    $resolver = new OutputPreferencesResolver();

    expect($resolver->resolve(new CommandRoute('validate'))->format())->toBe(OutputFormat::Terminal);
    expect($resolver->resolve(new CommandRoute('validate', globalOptions: ['json' => true]))->format())->toBe(OutputFormat::Json);
    expect($resolver->resolve(
        new CommandRoute('validate', globalOptions: ['no-json' => true]),
        outputResolverConfig('normal', false, false, 'json'),
    )->format())->toBe(OutputFormat::Terminal);
});

it('uses config before defaults', function (): void {
    $preferences = (new OutputPreferencesResolver())->resolve(
        route: new CommandRoute('validate'),
        config: outputResolverConfig('normal', false, false, 'json'),
    );

    expect($preferences->size())->toBe(OutputSize::Normal);
    expect($preferences->format())->toBe(OutputFormat::Json);
    expect($preferences->pretty())->toBeFalse();
    expect($preferences->showProcess())->toBeFalse();
});

it('disables pretty output by default in ci unless pretty is explicit', function (): void {
    $resolver = new OutputPreferencesResolver(runningInCi: true);

    $defaults = $resolver->resolve(new CommandRoute('validate'));
    $explicit = $resolver->resolve(new CommandRoute('validate', globalOptions: ['pretty' => true]));

    expect($defaults->pretty())->toBeFalse();
    expect($explicit->pretty())->toBeTrue();
});

it('rejects conflicting output flags in the same scope', function (): void {
    expect(fn(): mixed => (new OutputPreferencesResolver())->resolve(
        new CommandRoute('validate', globalOptions: ['compact' => true, 'full' => true]),
    ))->toThrow(InvalidUsageException::class, 'Options "--compact" and "--full" cannot be used together.');

    expect(fn(): mixed => (new OutputPreferencesResolver())->resolve(
        new CommandRoute('validate', globalOptions: ['pretty' => true, 'no-pretty' => true]),
    ))->toThrow(InvalidUsageException::class, 'Options "--pretty" and "--no-pretty" cannot be used together.');

    expect(fn(): mixed => (new OutputPreferencesResolver())->resolve(
        new CommandRoute('run.tool', globalOptions: ['json' => true, 'raw' => true]),
    ))->toThrow(InvalidUsageException::class, 'Options "--json" and "--raw" cannot be used together.');

    expect(fn(): mixed => (new OutputPreferencesResolver())->resolve(
        new CommandRoute('validate', globalOptions: ['json' => true, 'no-json' => true]),
    ))->toThrow(InvalidUsageException::class, 'Options "--json" and "--no-json" cannot be used together.');
});
