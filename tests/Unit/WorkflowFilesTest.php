<?php

declare(strict_types=1);

it('defines a dedicated CI coverage job with xdebug', function (): void {
    $workflow = (string) file_get_contents(siftRoot().DIRECTORY_SEPARATOR.'.github'.DIRECTORY_SEPARATOR.'workflows'.DIRECTORY_SEPARATOR.'ci.yml');

    expect($workflow)->toContain('coverage:')
        ->and($workflow)->toContain('php-coverage')
        ->and($workflow)->toContain('coverage: xdebug')
        ->and($workflow)->toContain('composer test:coverage')
        ->and($workflow)->toContain('actions/upload-artifact@v4')
        ->and($workflow)->toContain('build/coverage/clover.xml');
});

it('keeps the coverage clover path aligned across composer, docs, and ci', function (): void {
    $composer = json_decode((string) file_get_contents(siftRoot().DIRECTORY_SEPARATOR.'composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $docsReadme = (string) file_get_contents(siftRoot().DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'README.md');
    $coverageScript = is_array($composer['scripts']['test:coverage'])
        ? implode(' ', $composer['scripts']['test:coverage'])
        : (string) $composer['scripts']['test:coverage'];
    $workflow = (string) file_get_contents(siftRoot().DIRECTORY_SEPARATOR.'.github'.DIRECTORY_SEPARATOR.'workflows'.DIRECTORY_SEPARATOR.'ci.yml');

    expect($coverageScript)->toContain('--coverage --min=80')
        ->and($workflow)->toContain('composer test:coverage -- --coverage-clover=build/coverage/clover.xml')
        ->and($docsReadme)->toContain('build/coverage/clover.xml');
});
