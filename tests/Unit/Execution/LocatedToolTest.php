<?php

declare(strict_types=1);

use Sift\Execution\LocatedTool;

it('describes a resolved tool binary', function (): void {
    $tool = new LocatedTool(
        tool: 'pest',
        binary: '/project/vendor/bin/pest',
        candidate: 'vendor/bin/pest',
        source: 'relative',
    );

    expect($tool->tool())->toBe('pest');
    expect($tool->binary())->toBe('/project/vendor/bin/pest');
    expect($tool->candidate())->toBe('vendor/bin/pest');
    expect($tool->source())->toBe('relative');
});

it('rejects empty tool names and binaries', function (): void {
    expect(fn(): mixed => new LocatedTool(
        tool: '',
        binary: '/bin/tool',
        candidate: 'tool',
        source: 'path',
    ))->toThrow(InvalidArgumentException::class, 'Located tool name cannot be empty.');

    expect(fn(): mixed => new LocatedTool(
        tool: 'tool',
        binary: '',
        candidate: 'tool',
        source: 'path',
    ))->toThrow(InvalidArgumentException::class, 'Located tool binary cannot be empty.');
});
