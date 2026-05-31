<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Execution\ToolLocator;
use Tests\Support\FixtureProject;

it('locates absolute and relative binary paths', function (): void {
    $project = FixtureProject::create();
    $binary = $project->write('vendor/bin/pest', '');
    $locator = new ToolLocator(pathEnvironment: '');

    $absolute = $locator->locate('pest', $binary, $project->root());
    $relative = $locator->locate('pest', 'vendor/bin/pest', $project->root());

    expect($absolute->binary())->toBe($binary);
    expect($absolute->source())->toBe('absolute');
    expect($relative->binary())->toBe($binary);
    expect($relative->source())->toBe('relative');
});

it('locates command names on path', function (): void {
    $project = FixtureProject::create();
    $binary = $project->write('bin/pest', '');
    $locator = new ToolLocator(pathEnvironment: dirname($binary));

    $located = $locator->locate('pest', 'pest', $project->root());

    expect($located->binary())->toBe($binary);
    expect($located->candidate())->toBe('pest');
    expect($located->source())->toBe('path');
});

it('uses windows path extensions when resolving command names', function (): void {
    $project = FixtureProject::create();
    $binary = $project->write('bin/pest.bat', '');
    $locator = new ToolLocator(
        pathEnvironment: dirname($binary),
        pathExtensions: ['.bat'],
    );

    $located = $locator->locate('pest', 'pest', $project->root());

    expect($located->binary())->toBe($binary);
});

it('caches resolved binaries for the lifetime of the locator', function (): void {
    $project = FixtureProject::create();
    $binary = $project->write('bin/phpstan', '');
    $locator = new ToolLocator(pathEnvironment: dirname($binary));

    $first = $locator->locate('phpstan', 'phpstan', $project->root());
    unlink($binary);
    $second = $locator->locate('phpstan', 'phpstan', $project->root());

    expect($second->binary())->toBe($first->binary());
});

it('returns tool not found when no candidate can be located', function (): void {
    $project = FixtureProject::create();
    $locator = new ToolLocator(pathEnvironment: '');

    try {
        $locator->locate('pest', 'pest', $project->root());
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::ToolNotFound);
        expect($userFacingException->context())->toBe([
            'tool' => 'pest',
            'candidate' => 'pest',
        ]);

        return;
    }

    throw new RuntimeException('Tool locator did not fail.');
});

it('rejects an empty path separator', function (): void {
    expect(fn(): mixed => new ToolLocator(pathEnvironment: '', pathSeparator: ''))
        ->toThrow(InvalidArgumentException::class, 'Path separator cannot be empty.');
});
