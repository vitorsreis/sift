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
