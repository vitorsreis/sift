<?php

declare(strict_types=1);

use Sift\Config\ConfigValidationException;
use Sift\Console\InvalidUsageException;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Output\ErrorPayload;

it('builds the canonical error payload with hint and context', function (): void {
    $payload = ErrorPayload::make(
        errorCode: ErrorCode::PolicyBlocked,
        message: 'Execution blocked by policy.',
        hint: 'Remove the blocked option.',
        context: [
            'tool' => 'rector',
            'argument' => '--no-dry-run',
            'path' => null,
            'suggestions' => ['Use --dry-run.'],
        ],
    );

    expect($payload)->toBe([
        'status' => 'error',
        'error' => [
            'code' => 'policy_blocked',
            'message' => 'Execution blocked by policy.',
            'hint' => 'Remove the blocked option.',
            'tool' => 'rector',
            'argument' => '--no-dry-run',
            'suggestions' => ['Use --dry-run.'],
        ],
    ]);
});

it('builds invalid usage payloads', function (): void {
    $payload = ErrorPayload::fromInvalidUsage(new InvalidUsageException('Unknown option "--bad".'));

    expect($payload)->toBe([
        'status' => 'error',
        'error' => [
            'code' => 'invalid_usage',
            'message' => 'Unknown option "--bad".',
            'hint' => 'Run "sift help" to list available commands.',
        ],
    ]);
});

it('builds config validation payloads', function (): void {
    $payload = ErrorPayload::fromConfigValidation(
        ConfigValidationException::invalidConfig('/project/sift.json', 'Invalid config.'),
    );

    expect($payload)->toBe([
        'status' => 'error',
        'error' => [
            'code' => 'invalid_config',
            'message' => 'Invalid config.',
            'path' => '/project/sift.json',
        ],
    ]);
});

it('builds user-facing exception payloads', function (): void {
    $payload = ErrorPayload::fromUserFacing(UserFacingException::withContext(
        errorCode: ErrorCode::RunNotFound,
        message: 'Run not found.',
        hint: 'List available runs.',
        context: ['run_id' => 'run_123'],
    ));

    expect($payload)->toBe([
        'status' => 'error',
        'error' => [
            'code' => 'run_not_found',
            'message' => 'Run not found.',
            'hint' => 'List available runs.',
            'run_id' => 'run_123',
        ],
    ]);
});
