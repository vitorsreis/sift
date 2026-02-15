<?php

declare(strict_types=1);

namespace Sift\Tools\Concerns;

trait ResolvesToolCandidates
{
    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $fallbackCandidates
     * @return list<string>
     */
    private function resolveCandidates(array $context, array $fallbackCandidates): array
    {
        $configured = is_string($context['tool_binary'] ?? null) && trim((string) $context['tool_binary']) !== ''
            ? [trim((string) $context['tool_binary'])]
            : [];

        return $configured !== [] ? $configured : $fallbackCandidates;
    }
}
