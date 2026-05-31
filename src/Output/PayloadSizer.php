<?php

declare(strict_types=1);

namespace Sift\Output;

use Sift\Console\OutputPreferences;
use Sift\Console\OutputSize;

final class PayloadSizer
{
    private const array VERBOSE_ITEM_FIELDS = [
        'test' => true,
        'diff' => true,
        'stdout' => true,
        'stderr' => true,
        'trace' => true,
        'raw' => true,
    ];

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function resize(array $payload, OutputPreferences $preferences): array
    {
        if (($payload['status'] ?? null) === 'error') {
            return $payload;
        }

        return match ($preferences->size()) {
            OutputSize::Compact => $this->compact($payload),
            OutputSize::Normal => $this->normal($payload),
            OutputSize::Full => $payload,
        };
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function compact(array $payload): array
    {
        $compact = [];

        foreach (['tool', 'status'] as $key) {
            if (array_key_exists($key, $payload)) {
                $compact[$key] = $payload[$key];
            }
        }

        $summary = $payload['summary'] ?? [];

        if (! is_array($summary) || array_is_list($summary)) {
            return $compact;
        }

        foreach ($summary as $key => $value) {
            if (is_string($key) && ! array_key_exists($key, $compact)) {
                $compact[$key] = $value;
            }
        }

        return $compact;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function normal(array $payload): array
    {
        $normal = [];

        foreach (['tool', 'status', 'summary'] as $key) {
            if (array_key_exists($key, $payload)) {
                $normal[$key] = $payload[$key];
            }
        }

        $normal['items'] = $this->normalItems($payload['items'] ?? []);

        if (array_key_exists('meta', $payload)) {
            $normal['meta'] = $payload['meta'];
        }

        return $normal;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalItems(mixed $items): array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            return [];
        }

        $normalItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (array_is_list($item)) {
                continue;
            }

            $normalItem = [];

            foreach ($item as $key => $value) {
                if (is_string($key) && ! array_key_exists($key, self::VERBOSE_ITEM_FIELDS)) {
                    $normalItem[$key] = $value;
                }
            }

            $normalItems[] = $normalItem;
        }

        return $normalItems;
    }
}
