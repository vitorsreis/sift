<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\Platform;
use Sift\Execution\ToolLocator;
use Sift\Execution\ToolResolver;
use Sift\Tools\ToolDefinition;
use Tests\Support\FixtureProject;

it('uses configured binary before definition candidates', function (): void {
    $project = FixtureProject::create();
    $configured = $project->write('custom/pest', '');
    $candidate = $project->write('vendor/bin/pest', '');

    $resolver = new ToolResolver(new ToolLocator(pathEnvironment: ''));
    $located = $resolver->resolve(
        definition: new ToolDefinition(
            name: 'pest',
            aliases: ['test'],
            description: 'Pest test runner.',
            binaryCandidates: [$candidate],
            installHint: 'composer require --dev pestphp/pest',
            defaultContext: 'test',
        ),
        config: new ToolConfig('pest', true, $configured, [], 1800),
        cwd: $project->root(),
    );

    expect($located->binary())->toBe($configured);
    expect($located->candidate())->toBe($configured);
});

it('falls back through definition candidates when no binary is configured', function (): void {
    $project = FixtureProject::create();
    $binary = $project->write('vendor/bin/pest', '');

    $resolver = new ToolResolver(new ToolLocator(pathEnvironment: ''));
    $located = $resolver->resolve(
        definition: new ToolDefinition(
            name: 'pest',
            aliases: ['test'],
            description: 'Pest test runner.',
            binaryCandidates: ['missing-pest', 'vendor/bin/pest'],
            installHint: 'composer require --dev pestphp/pest',
            defaultContext: 'test',
        ),
        config: new ToolConfig('pest', true, null, [], 1800),
        cwd: $project->root(),
    );

    expect($located->binary())->toBe($binary);
    expect($located->candidate())->toBe('vendor/bin/pest');
});

it('skips windows launcher candidates on non-windows platforms', function (): void {
    $project = FixtureProject::create();
    $project->write('vendor/bin/pest.bat', '');

    $binary = $project->write('vendor/bin/pest', '');

    $resolver = new ToolResolver(
        new ToolLocator(pathEnvironment: ''),
        new Platform('Linux'),
    );
    $located = $resolver->resolve(
        definition: new ToolDefinition(
            name: 'pest',
            aliases: ['test'],
            description: 'Pest test runner.',
            binaryCandidates: ['vendor/bin/pest.bat', 'vendor/bin/pest'],
            installHint: 'composer require --dev pestphp/pest',
            defaultContext: 'test',
        ),
        config: new ToolConfig('pest', true, null, [], 1800),
        cwd: $project->root(),
    );

    expect($located->binary())->toBe($binary);
    expect($located->candidate())->toBe('vendor/bin/pest');
});

it('fails before resolution when a tool is disabled', function (): void {
    $project = FixtureProject::create();
    $resolver = new ToolResolver(new ToolLocator(pathEnvironment: ''));

    try {
        $resolver->resolve(
            definition: new ToolDefinition(
                name: 'pest',
                aliases: ['test'],
                description: 'Pest test runner.',
                binaryCandidates: ['pest'],
                installHint: 'composer require --dev pestphp/pest',
                defaultContext: 'test',
            ),
            config: new ToolConfig('pest', false, null, [], 1800),
            cwd: $project->root(),
        );
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::ToolDisabled);
        expect($userFacingException->context())->toBe(['tool' => 'pest']);

        return;
    }

    throw new RuntimeException('Tool resolver did not fail.');
});
