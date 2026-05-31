<?php

declare(strict_types=1);

use Sift\Tools\MutationPolicy;
use Sift\Tools\ToolDefinition;

it('describes a supported tool contract', function (): void {
    $definition = new ToolDefinition(
        name: 'pint',
        aliases: ['format'],
        description: 'Laravel Pint formatter.',
        binaryCandidates: ['vendor/bin/pint.bat', 'vendor/bin/pint', 'pint'],
        installHint: 'composer require --dev laravel/pint',
        defaultContext: 'test',
        versionCommand: ['--version'],
        mutationPolicy: MutationPolicy::RepairFlag,
        repairCommand: ['--repair'],
    );

    expect($definition->name())->toBe('pint');
    expect($definition->aliases())->toBe(['format']);
    expect($definition->description())->toBe('Laravel Pint formatter.');
    expect($definition->binaryCandidates())->toBe(['vendor/bin/pint.bat', 'vendor/bin/pint', 'pint']);
    expect($definition->versionCommand())->toBe(['--version']);
    expect($definition->installHint())->toBe('composer require --dev laravel/pint');
    expect($definition->defaultContext())->toBe('test');
    expect($definition->mutationPolicy())->toBe(MutationPolicy::RepairFlag);
    expect($definition->repairCommand())->toBe(['--repair']);
});

it('rejects empty names and binary candidates', function (): void {
    expect(fn(): mixed => new ToolDefinition(
        name: '',
        aliases: [],
        description: 'Tool.',
        binaryCandidates: ['tool'],
        installHint: 'Install tool.',
        defaultContext: 'check',
    ))->toThrow(InvalidArgumentException::class, 'Tool definition name cannot be empty.');

    expect(fn(): mixed => new ToolDefinition(
        name: 'tool',
        aliases: [],
        description: 'Tool.',
        binaryCandidates: [],
        installHint: 'Install tool.',
        defaultContext: 'check',
    ))->toThrow(InvalidArgumentException::class, 'Tool definition must include at least one binary candidate.');
});
