<?php

declare(strict_types=1);

use Sift\Core\ExecutionResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;
use Sift\Tools\ComposerAuditToolAdapter;
use Sift\Tools\PhpcsToolAdapter;
use Sift\Tools\PhpstanToolAdapter;
use Sift\Tools\PintToolAdapter;
use Sift\Tools\PsalmToolAdapter;

it('reports parse failure when composer audit json output is invalid', function (): void {
    $adapter = new ComposerAuditToolAdapter(new ToolLocator);

    try {
        $adapter->parse(
            new ExecutionResult(1, 'not-json', 'stderr', 12),
            new PreparedCommand(['composer', 'audit'], siftRoot()),
            [],
        );
        $this->fail('Expected parse failure.');
    } catch (UserFacingException $exception) {
        expect($exception->payload()['error']['code'])->toBe('parse_failure')
            ->and($exception->payload()['error']['tool'])->toBe('composer-audit');
    }
});

it('reports parse failure when phpcs json output is invalid', function (): void {
    $adapter = new PhpcsToolAdapter(new ToolLocator);

    try {
        $adapter->parse(
            new ExecutionResult(1, 'not-json', 'stderr', 12),
            new PreparedCommand(['phpcs'], siftRoot()),
            [],
        );
        $this->fail('Expected parse failure.');
    } catch (UserFacingException $exception) {
        expect($exception->payload()['error']['code'])->toBe('parse_failure')
            ->and($exception->payload()['error']['tool'])->toBe('phpcs');
    }
});

it('reports parse failure when phpstan json output is invalid', function (): void {
    $adapter = new PhpstanToolAdapter(new ToolLocator);

    try {
        $adapter->parse(
            new ExecutionResult(1, 'not-json', 'stderr', 12),
            new PreparedCommand(['phpstan'], siftRoot()),
            [],
        );
        $this->fail('Expected parse failure.');
    } catch (UserFacingException $exception) {
        expect($exception->payload()['error']['code'])->toBe('parse_failure')
            ->and($exception->payload()['error']['tool'])->toBe('phpstan');
    }
});

it('reports parse failure when psalm json output is invalid', function (): void {
    $adapter = new PsalmToolAdapter(new ToolLocator);

    try {
        $adapter->parse(
            new ExecutionResult(1, 'not-json', 'stderr', 12),
            new PreparedCommand(['psalm'], siftRoot()),
            [],
        );
        $this->fail('Expected parse failure.');
    } catch (UserFacingException $exception) {
        expect($exception->payload()['error']['code'])->toBe('parse_failure')
            ->and($exception->payload()['error']['tool'])->toBe('psalm');
    }
});

it('reports parse failure when pint json output is invalid', function (): void {
    $adapter = new PintToolAdapter(new ToolLocator);

    try {
        $adapter->parse(
            new ExecutionResult(1, "noise\nstill-not-json", 'stderr', 12),
            new PreparedCommand(['pint'], siftRoot()),
            [],
        );
        $this->fail('Expected parse failure.');
    } catch (UserFacingException $exception) {
        expect($exception->payload()['error']['code'])->toBe('parse_failure')
            ->and($exception->payload()['error']['tool'])->toBe('pint');
    }
});
