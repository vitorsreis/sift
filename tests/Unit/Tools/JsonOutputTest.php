<?php

declare(strict_types=1);

use Sift\Tools\JsonOutput;

it('keeps decoded json with raw source metadata', function (): void {
    $output = new JsonOutput(
        decoded: ['totals' => ['errors' => 1]],
        raw: '{"totals":{"errors":1}}',
        source: 'stdout',
        line: 3,
        offset: 42,
        clean: false,
    );

    expect($output->decoded())->toBe(['totals' => ['errors' => 1]]);
    expect($output->object())->toBe(['totals' => ['errors' => 1]]);
    expect($output->raw())->toBe('{"totals":{"errors":1}}');
    expect($output->source())->toBe('stdout');
    expect($output->line())->toBe(3);
    expect($output->offset())->toBe(42);
    expect($output->clean())->toBeFalse();
});

it('rejects non-object decoded access', function (): void {
    $output = new JsonOutput(
        decoded: [['message' => 'issue']],
        raw: '[{"message":"issue"}]',
        source: 'stdout',
    );

    expect(fn(): mixed => $output->object())
        ->toThrow(UnexpectedValueException::class, 'Decoded JSON root must be an object.');
});
