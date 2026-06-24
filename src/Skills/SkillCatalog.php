<?php

declare(strict_types=1);

namespace Sift\Skills;

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;

/**
 * @phpstan-type CatalogItem array{name: string, description: string, source: string, skills: list<string>, agents: list<string>, tags: list<string>, slug?: string, installs?: int}
 */
final readonly class SkillCatalog
{
    public function __construct(
        private SkillsShCatalogClient $client = new SkillsShCatalogClient(),
    ) {}

    /**
     * @return list<CatalogItem>
     */
    public function search(string $query, ?string $owner = null): array
    {
        return $this->normalize($this->client->search($query, owner: $owner));
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @return list<CatalogItem>
     */
    public function normalize(array $payload): array
    {
        $items = array_is_list($payload) ? $payload : ($payload['items'] ?? $payload['skills'] ?? null);

        if (! is_array($items) || ! array_is_list($items)) {
            $this->unavailable('The skill catalog response does not contain an item list.');
        }

        $normalized = [];
        $hasInstalls = false;

        foreach ($items as $item) {
            if (! is_array($item) || array_is_list($item)) {
                $this->unavailable('The skill catalog response contains an invalid item.');
            }

            $normalizedItem = $this->normalizeItem($this->objectItem($item));
            $hasInstalls = $hasInstalls || array_key_exists('installs', $normalizedItem);
            $normalized[] = $normalizedItem;
        }

        if ($hasInstalls) {
            usort(
                $normalized,
                static fn(array $left, array $right): int => ($right['installs'] ?? 0) <=> ($left['installs'] ?? 0),
            );
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectItem(mixed $item): array
    {
        if (! is_array($item) || array_is_list($item)) {
            $this->unavailable('The skill catalog response contains an invalid item.');
        }

        $object = [];

        foreach ($item as $key => $value) {
            if (! is_string($key)) {
                $this->unavailable('The skill catalog response contains an invalid item key.');
            }

            $object[$key] = $value;
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return CatalogItem
     */
    private function normalizeItem(array $item): array
    {
        $name = $this->requiredString($item, 'name');

        if (preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $name) !== 1) {
            $this->unavailable('The skill catalog response contains an invalid skill name.', ['name' => $name]);
        }

        $normalized = [
            'name' => $name,
            'description' => $this->optionalString($item, 'description') ?? sprintf('Use the %s skill.', $name),
            'source' => $this->requiredString($item, 'source'),
            'skills' => $this->stringList($item['skills'] ?? [], 'skills'),
            'agents' => $this->stringList($item['agents'] ?? [], 'agents'),
            'tags' => $this->stringList($item['tags'] ?? [], 'tags'),
        ];

        $slug = $this->optionalString($item, 'id') ?? $this->optionalString($item, 'slug');
        if ($slug !== null) {
            $normalized['slug'] = $slug;
        }

        $installs = $this->optionalNonNegativeInt($item, 'installs');
        if ($installs !== null) {
            $normalized['installs'] = $installs;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function requiredString(array $item, string $field): string
    {
        $value = $item[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            $this->unavailable(sprintf('The skill catalog response is missing "%s".', $field));
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function optionalString(array $item, string $field): ?string
    {
        $value = $item[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            $this->unavailable(sprintf('The skill catalog response field "%s" must be a string.', $field));
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function optionalNonNegativeInt(array $item, string $field): ?int
    {
        $value = $item[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value) || $value < 0) {
            $this->unavailable(sprintf('The skill catalog response field "%s" must be a non-negative integer.', $field));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $this->unavailable(sprintf('The skill catalog response field "%s" must be a list.', $field));
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                $this->unavailable(sprintf('The skill catalog response field "%s" contains an invalid value.', $field));
            }

            $item = trim($item);

            if (! in_array($item, $items, true)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function unavailable(string $message, array $context = []): never
    {
        throw UserFacingException::withContext(
            errorCode: ErrorCode::SkillCatalogUnavailable,
            message: $message,
            context: $context,
        );
    }
}
