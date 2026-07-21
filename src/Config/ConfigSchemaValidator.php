<?php

declare(strict_types=1);

namespace Sift\Config;

final readonly class ConfigSchemaValidator
{
    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $schema
     */
    public function validate(array $document, array $schema, ?string $configPath): void
    {
        $this->validateValue($document, $schema, $schema, 'config', $configPath);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $rootSchema
     */
    private function validateValue(mixed $value, array $schema, array $rootSchema, string $path, ?string $configPath): void
    {
        $reference = $schema['$ref'] ?? null;

        if (is_string($reference)) {
            $this->validateValue($value, $this->resolveReference($reference, $rootSchema, $configPath), $rootSchema, $path, $configPath);
            unset($schema['$ref']);
        }

        $this->validateType($value, $schema['type'] ?? null, $path, $configPath);
        $this->validateEnum($value, $schema['enum'] ?? null, $path, $configPath);

        if (($schema['type'] ?? null) === 'object' && is_array($value)) {
            $this->validateObject($value, $schema, $rootSchema, $path, $configPath);
        }

        if (($schema['type'] ?? null) === 'array' && is_array($value)) {
            $this->validateArray($value, $schema, $rootSchema, $path, $configPath);
        }

        if (is_string($value)) {
            $this->validateString($value, $schema, $path, $configPath);
        }

        if (is_int($value)) {
            $this->validateInteger($value, $schema, $path, $configPath);
        }
    }

    /**
     * @param array<mixed> $value
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $rootSchema
     */
    private function validateObject(array $value, array $schema, array $rootSchema, string $path, ?string $configPath): void
    {
        if ($value !== [] && array_is_list($value)) {
            $this->fail($configPath, $path, 'must be an object');
        }

        $properties = $this->object($schema['properties'] ?? []);
        $additionalProperties = $schema['additionalProperties'] ?? true;

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                $this->fail($configPath, $path, 'must use string keys');
            }

            $propertySchema = $properties[$key] ?? $additionalProperties;

            if ($propertySchema === false) {
                $this->fail($configPath, $path . '.' . $key, 'is not allowed');
            }

            if (is_array($propertySchema) && ! array_is_list($propertySchema)) {
                $this->validateValue($item, $this->object($propertySchema), $rootSchema, $path . '.' . $key, $configPath);
            }
        }
    }

    /**
     * @param array<mixed> $value
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $rootSchema
     */
    private function validateArray(array $value, array $schema, array $rootSchema, string $path, ?string $configPath): void
    {
        if (! array_is_list($value)) {
            $this->fail($configPath, $path, 'must be an array');
        }

        $itemSchema = $schema['items'] ?? null;

        if (! is_array($itemSchema) || array_is_list($itemSchema)) {
            return;
        }

        foreach ($value as $index => $item) {
            $this->validateValue($item, $this->object($itemSchema), $rootSchema, sprintf('%s[%d]', $path, $index), $configPath);
        }
    }

    /**
     * @param array<string, mixed> $rootSchema
     *
     * @return array<string, mixed>
     */
    private function resolveReference(string $reference, array $rootSchema, ?string $configPath): array
    {
        if (! str_starts_with($reference, '#/')) {
            $this->fail($configPath, '$schema.$ref', 'must be a local JSON Pointer');
        }

        $resolved = $rootSchema;

        foreach (explode('/', substr($reference, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if (! array_key_exists($segment, $resolved)) {
                $this->fail($configPath, '$schema.$ref', sprintf('references missing segment %s', $segment));
            }

            $value = $resolved[$segment];

            if (! is_array($value) || array_is_list($value)) {
                $this->fail($configPath, '$schema.$ref', 'must resolve to a schema object');
            }

            $resolved = $this->object($value);
        }

        return $resolved;
    }

    private function validateType(mixed $value, mixed $type, string $path, ?string $configPath): void
    {
        $valid = match ($type) {
            null => true,
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            default => false,
        };

        if (! $valid) {
            $this->fail($configPath, $path, sprintf('must be of type %s', is_string($type) ? $type : 'supported'));
        }
    }

    private function validateEnum(mixed $value, mixed $enum, string $path, ?string $configPath): void
    {
        if (is_array($enum) && array_is_list($enum) && ! in_array($value, $enum, true)) {
            $this->fail($configPath, $path, 'must contain an allowed value');
        }
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function validateString(string $value, array $schema, string $path, ?string $configPath): void
    {
        $minLength = $schema['minLength'] ?? null;

        if (is_int($minLength) && strlen($value) < $minLength) {
            $this->fail($configPath, $path, sprintf('must contain at least %d character(s)', $minLength));
        }
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function validateInteger(int $value, array $schema, string $path, ?string $configPath): void
    {
        $minimum = $schema['minimum'] ?? null;

        if (is_int($minimum) && $value < $minimum) {
            $this->fail($configPath, $path, sprintf('must be greater than or equal to %d', $minimum));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value)) {
            return [];
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $object[$key] = $item;
            }
        }

        return $object;
    }

    private function fail(?string $configPath, string $path, string $reason): never
    {
        throw ConfigValidationException::invalidConfig(
            $configPath,
            sprintf('JSON Schema validation failed: `%s` %s.', $path, $reason),
        );
    }
}
