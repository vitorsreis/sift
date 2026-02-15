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

            $candidate = $this->extractJsonValue($stream);

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

    private function extractJsonValue(string $stream): ?string
    {
        $objectStart = strpos($stream, '{');
        $arrayStart = strpos($stream, '[');

        if ($objectStart === false && $arrayStart === false) {
            return null;
        }

        $start = $this->firstJsonStart($objectStart, $arrayStart);
        $opening = $stream[$start];
        $closing = $opening === '{' ? '}' : ']';
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($stream);

        for ($index = $start; $index < $length; $index++) {
            $character = $stream[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($character === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($character === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;

                continue;
            }

            if ($character === $opening) {
                $depth++;

                continue;
            }

            if ($character === $closing) {
                $depth--;

                if ($depth === 0) {
                    return substr($stream, $start, $index - $start + 1);
                }
            }
        }

        return null;
    }

    private function firstJsonStart(int|false $objectStart, int|false $arrayStart): int
    {
        if ($objectStart === false) {
            return (int) $arrayStart;
        }

        if ($arrayStart === false) {
            return (int) $objectStart;
        }

        return min($objectStart, $arrayStart);
    }
}
