<?php

declare(strict_types=1);

use Sift\History\RunIdFormat;

it('validates and extracts sortable run ids', function (): void {
    $runId = '0tg42hl0w5a2v9';

    expect(RunIdFormat::isValid($runId))->toBeTrue();
    expect(RunIdFormat::isCore($runId))->toBeTrue();
    expect(RunIdFormat::core($runId))->toBe($runId);
    expect(RunIdFormat::fileCore('sift_' . $runId . '_composer-validate'))->toBe($runId);
    expect(RunIdFormat::fileId($runId, 'Composer Validate'))->toBe('sift_' . $runId . '_composer-validate');
    expect(RunIdFormat::createdAt($runId)?->getTimestamp())->toBeGreaterThan(0);
});

it('rejects invalid run ids and normalizes tool slugs', function (): void {
    expect(RunIdFormat::isValid('old-id'))->toBeFalse();
    expect(RunIdFormat::core('old-id'))->toBeNull();
    expect(RunIdFormat::fileCore('sift_old-id_pest'))->toBeNull();
    expect(RunIdFormat::fileId('old-id', 'pest'))->toBeNull();
    expect(RunIdFormat::createdAt('old-id'))->toBeNull();
    expect(RunIdFormat::toolSlug('  !!!  '))->toBe('unknown');
    expect(RunIdFormat::toolSlug('composer validate --strict'))->toBe('composer-validate-strict');
});
