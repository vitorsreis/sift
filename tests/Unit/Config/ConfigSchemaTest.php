<?php

declare(strict_types=1);

use Sift\Config\ConfigDefaults;

/**
 * @param array<string, mixed> $object
 *
 * @return array<string, mixed>
 */
function configSchemaObject(array $object, string $key): array
{
    $value = $object[$key] ?? null;

    if (! is_array($value) || array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected schema object "%s".', $key));
    }

    $normalized = [];

    foreach ($value as $field => $fieldValue) {
        if (! is_string($field)) {
            throw new RuntimeException(sprintf('Expected string keys in schema object "%s".', $key));
        }

        $normalized[$field] = $fieldValue;
    }

    return $normalized;
}

it('keeps schema, defaults and documentation on the same contract url and fields', function (): void {
    $root = dirname(__DIR__, 3);
    $decodedSchema = json_decode((string) file_get_contents($root . '/resources/schema.json'), true, 512, JSON_THROW_ON_ERROR);
    $documentation = (string) file_get_contents($root . '/docs/configuration.md');

    if (! is_array($decodedSchema) || array_is_list($decodedSchema)) {
        throw new RuntimeException('Config schema root must be an object.');
    }

    $schema = configSchemaObject(['schema' => $decodedSchema], 'schema');
    $properties = configSchemaObject($schema, 'properties');
    $history = configSchemaObject($properties, 'history');
    $historyProperties = configSchemaObject($history, 'properties');
    $output = configSchemaObject($properties, 'output');
    $outputProperties = configSchemaObject($output, 'properties');
    $tools = configSchemaObject($properties, 'tools');
    $toolProperties = configSchemaObject(configSchemaObject($tools, 'additionalProperties'), 'properties');

    expect($schema['$id'] ?? null)->toBe(ConfigDefaults::schemaUrl());
    expect($historyProperties)->toHaveKeys([
        'enabled',
        'path',
        'max_files',
        'max_age_days',
        'max_bytes_per_run',
        'redact_secrets',
    ]);
    expect($outputProperties)->toHaveKeys([
        'size',
        'pretty',
        'show_process',
    ]);
    expect($toolProperties)->toHaveKeys([
        'enabled',
        'binary',
        'blocked_args',
        'timeout',
    ]);

    expect($documentation)->toContain(ConfigDefaults::schemaUrl());
    expect($documentation)->toContain('max_age_days');
    expect($documentation)->toContain('max_bytes_per_run');
    expect($documentation)->toContain('redact_secrets');
});
