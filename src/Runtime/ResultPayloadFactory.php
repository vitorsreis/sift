<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Core\NormalizedResult;

final class ResultPayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function forSize(NormalizedResult $result, string $size): array
    {
        return match ($size) {
            'compact' => $this->compact($result),
            'fuller' => $this->fuller($result),
            default => $this->normal($result),
        };
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
            'items' => $result->items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fuller(NormalizedResult $result): array
    {
        return $result->toArray();
    }
}
