<?php

declare(strict_types=1);

use Sift\Renderers\JsonRenderer;
use Sift\Renderers\MarkdownRenderer;

it('renders compact json output with unescaped slashes by default', function (): void {
    $renderer = new JsonRenderer;

    $rendered = $renderer->render([
        'status' => 'ok',
        'path' => 'vendor/bin/demo',
    ]);

    expect($rendered)->toBe('{"status":"ok","path":"vendor/bin/demo"}');
});

it('renders pretty json output when requested', function (): void {
    $renderer = new JsonRenderer;

    $rendered = $renderer->render([
        'status' => 'ok',
        'items' => ['demo'],
    ], true);

    expect($rendered)->toContain("\n")
        ->and($rendered)->toContain('    "demo"');
});

it('renders markdown for scalars arrays and empty collections', function (): void {
    $renderer = new MarkdownRenderer;

    $rendered = $renderer->render([
        'status' => 'ok',
        'passed' => true,
        'notes' => null,
        'items' => [
            'first',
            ['tool' => 'demo', 'status' => 'ok'],
        ],
        'summary' => [],
    ]);

    expect($rendered)->toContain('- **status:** ok')
        ->and($rendered)->toContain('- **passed:** true')
        ->and($rendered)->toContain('- **notes:** null')
        ->and($rendered)->toContain('**items**')
        ->and($rendered)->toContain('- first')
        ->and($rendered)->toContain('- `1`: `{"tool":"demo","status":"ok"}`')
        ->and($rendered)->toContain('**summary**')
        ->and($rendered)->toContain('- empty');
});
