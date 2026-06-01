<?php

declare(strict_types=1);

namespace Sift\Skills;

final readonly class SkillManagedMetadata
{
    /**
     * @param list<string> $targets
     */
    public function __construct(
        private string $name,
        private string $source,
        private string $sourceType,
        private ?string $resolvedRef,
        private string $installedAt,
        private array $targets,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload, string $fallbackName): ?self
    {
        $name = self::string($payload['name'] ?? null) ?? $fallbackName;

        if (preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $name) !== 1) {
            return null;
        }

        $source = self::string($payload['source'] ?? null);
        $sourceType = self::string($payload['source_type'] ?? null);
        $installedAt = self::string($payload['installed_at'] ?? null);
        $targets = self::stringList($payload['targets'] ?? null);

        if ($source === null || $sourceType === null || $installedAt === null || $targets === []) {
            return null;
        }

        return new self(
            name: $name,
            source: $source,
            sourceType: $sourceType,
            resolvedRef: self::string($payload['resolved_ref'] ?? null),
            installedAt: $installedAt,
            targets: $targets,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function source(): string
    {
        return $this->source;
    }

    /**
     * @return list<string>
     */
    public function targets(): array
    {
        return $this->targets;
    }

    /**
     * @return array<string, mixed>
     */
    public function toItem(): array
    {
        return [
            'name' => $this->name,
            'source' => $this->source,
            'source_type' => $this->sourceType,
            'resolved_ref' => $this->resolvedRef,
            'installed_at' => $this->installedAt,
            'targets' => $this->targets,
        ];
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $strings[] = $item;
            }
        }

        return array_values(array_unique($strings));
    }
}
