<?php

declare(strict_types=1);

use Sift\Skills\ManagedBlockEditor;

it('inserts and replaces one managed skill block with encoded metadata', function (): void {
    $editor = new ManagedBlockEditor();

    $first = $editor->upsert(
        "Manual header\n",
        'php-review',
        ['name' => 'php-review', 'source' => 'repo', 'targets' => ['generic']],
        "First body\n",
    );
    $second = $editor->upsert(
        $first,
        'php-review',
        ['name' => 'php-review', 'source' => 'repo', 'targets' => ['generic']],
        "Second body\n",
    );

    expect($first)->toContain('Manual header');
    expect($first)->toContain('<!-- sift:skill:php-review:start data="');
    expect($first)->toContain('First body');
    expect($second)->toContain('Manual header');
    expect($second)->toContain('Second body');
    expect($second)->not->toContain('First body');
    expect(substr_count($second, '<!-- sift:skill:php-review:start'))->toBe(1);
    expect(substr_count($second, '<!-- sift:skill:php-review:end -->'))->toBe(1);
});

it('reads metadata from managed skill blocks', function (): void {
    $editor = new ManagedBlockEditor();
    $contents = $editor->upsert(
        '',
        'php-review',
        [
            'name' => 'php-review',
            'source' => 'repo',
            'source_type' => 'local',
            'installed_at' => '2026-06-01T00:00:00+00:00',
            'targets' => ['generic'],
        ],
        "Body\n",
    );

    $metadata = $editor->metadata($contents);

    expect($metadata)->toHaveCount(1);
    expect($metadata[0]->name())->toBe('php-review');
    expect($metadata[0]->source())->toBe('repo');
    expect($metadata[0]->targets())->toBe(['generic']);
});

it('removes only the requested managed block', function (): void {
    $editor = new ManagedBlockEditor();
    $contents = "Manual header\n\n";
    $contents = $editor->upsert($contents, 'php-review', [
        'name' => 'php-review',
        'source' => 'repo',
        'source_type' => 'local',
        'installed_at' => '2026-06-01T00:00:00+00:00',
        'targets' => ['generic'],
    ], "PHP body\n");
    $contents = $editor->upsert($contents, 'laravel-review', [
        'name' => 'laravel-review',
        'source' => 'repo',
        'source_type' => 'local',
        'installed_at' => '2026-06-01T00:00:00+00:00',
        'targets' => ['generic'],
    ], "Laravel body\n");

    $removed = $editor->remove($contents, 'php-review');

    expect($removed)->toContain('Manual header');
    expect($removed)->not->toContain('sift:skill:php-review:start');
    expect($removed)->toContain('sift:skill:laravel-review:start');
});
