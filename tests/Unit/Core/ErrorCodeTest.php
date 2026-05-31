<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;

it('catalogs all public error codes', function (): void {
    expect(array_column(ErrorCode::cases(), 'value'))->toBe([
        'invalid_usage',
        'invalid_config',
        'config_schema_unsupported',
        'php_version_unsupported',
        'extension_missing',
        'tool_not_found',
        'tool_disabled',
        'blocked_argument',
        'policy_blocked',
        'unsupported_tool_version',
        'process_failed',
        'process_timeout',
        'process_interrupted',
        'parse_failure',
        'filesystem_error',
        'history_write_failed',
        'run_not_found',
        'skill_catalog_unavailable',
        'skill_source_not_found',
        'skill_selection_required',
        'unsupported_target',
        'github_clone_failed',
        'phar_resource_missing',
    ]);
});

it('keeps error code values unique', function (): void {
    $values = array_column(ErrorCode::cases(), 'value');

    expect(array_unique($values))->toHaveCount(count($values));
});
