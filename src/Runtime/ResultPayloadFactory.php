<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Core\NormalizedResult;

final class ResultPayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function forSize(NormalizedResult $result, string $size, ?string $runId = null): array
    {
        $payload = match ($size) {
            'compact' => $this->compact($result),
            'fuller' => $this->fuller($result),
            default => $this->normal($result),
        };

        if ($runId !== null) {
            $payload['run_id'] = $runId;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function commandPayload(array $payload, string $size): array
    {
        if ($size !== 'compact') {
            return $payload;
        }

        $compact = [
            'status' => (string) ($payload['status'] ?? 'ok'),
        ];

        foreach (['tool', 'version', 'tools'] as $key) {
            if (array_key_exists($key, $payload)) {
                $compact[$key] = $payload[$key];
            }
        }

        return $compact;
    }

    /**
     * @return array<string, mixed>
     */
    private function compact(NormalizedResult $result): array
    {
        return [
            'status' => $result->status,
            ...$result->summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normal(NormalizedResult $result): array
    {
        return [
            'status' => $result->status,
            'summary' => $result->summary,
            'items' => $this->normalItems($result->items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fuller(NormalizedResult $result): array
    {
        return $result->toArray();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            unset($item['test']);
            $normalized[] = $item;
        }

        return $normalized;
    }
}
