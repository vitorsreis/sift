<?php

declare(strict_types=1);

it('defines a dedicated CI coverage job with xdebug', function (): void {
    $workflow = (string) file_get_contents(siftRoot().DIRECTORY_SEPARATOR.'.github'.DIRECTORY_SEPARATOR.'workflows'.DIRECTORY_SEPARATOR.'ci.yml');

    expect($workflow)->toContain('- master')
        ->and($workflow)->toContain('coverage:')
        ->and($workflow)->toContain('php-coverage')
        ->and($workflow)->toContain('coverage: xdebug')
        ->and($workflow)->toContain('composer test:coverage')
        ->and($workflow)->toContain('actions/upload-artifact@v4')
        ->and($workflow)->toContain('build/coverage/clover.xml');
});

it('keeps docs readme focused on product usage instead of internal test flow', function (): void {
    $docsReadme = (string) file_get_contents(siftRoot().DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'README.md');

    expect($docsReadme)->not->toContain('composer test:coverage')
        ->and($docsReadme)->not->toContain('build/coverage/clover.xml')
        ->and($docsReadme)->not->toContain('php-coverage')
        ->and($docsReadme)->toContain('[COMMANDS.md](COMMANDS.md)')
        ->and($docsReadme)->toContain('[CONFIGURATION.md](CONFIGURATION.md)');
});
