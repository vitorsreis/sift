<?php

declare(strict_types=1);

namespace Sift\Tools\Concerns;

use Sift\Core\ExecutionResult;

trait DecodesJsonOutput
{
    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonOutput(ExecutionResult $executionResult, bool $allowNoisy = false): ?array
    {
        $streams = [$executionResult->stdout, $executionResult->stderr];

        foreach ($streams as $stream) {
            $decoded = json_decode($stream, true);

            if (is_array($decoded)) {
                return $decoded;
            }

            if (! $allowNoisy) {
                continue;
            }

            $candidate = $this->extractJsonObject($stream);

            if ($candidate === null) {
                continue;
            }

            $decoded = json_decode($candidate, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function extractJsonObject(string $stream): ?string
    {
        $start = strpos($stream, '{');
        $end = strrpos($stream, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($stream, $start, $end - $start + 1);
    }
}
