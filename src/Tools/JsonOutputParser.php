<?php

declare(strict_types=1);

namespace Sift\Tools;

use JsonException;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;

final readonly class JsonOutputParser
{
    private const int SNIPPET_LIMIT = 4000;

    public function parse(string $stdout, string $stderr = ''): JsonOutput
    {
        foreach ([['stdout', $stdout], ['stderr', $stderr]] as [$source, $raw]) {
            $clean = $this->parseClean($raw, $source);

            if ($clean instanceof JsonOutput) {
                return $clean;
            }
        }

        foreach ([['stdout', $stdout], ['stderr', $stderr]] as [$source, $raw]) {
            $noisy = $this->parseNoisy($raw, $source);

            if ($noisy instanceof JsonOutput) {
                return $noisy;
            }
        }

        throw UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Could not parse tool JSON output.',
            hint: 'Use --debug to inspect limited raw output snippets.',
            context: [
                'stdout' => $this->snippet($stdout),
                'stderr' => $this->snippet($stderr),
            ],
        );
    }

    private function parseClean(string $raw, string $source): ?JsonOutput
    {
        $candidate = trim($raw);

        if ($candidate === '') {
            return null;
        }

        try {
            return new JsonOutput(
                decoded: json_decode($candidate, true, 512, JSON_THROW_ON_ERROR),
                raw: $candidate,
                source: $source,
                clean: true,
            );
        } catch (JsonException) {
            return null;
        }
    }

    private function parseNoisy(string $raw, string $source): ?JsonOutput
    {
        if (trim($raw) === '') {
            return null;
        }

        $lines = preg_split('/\R/', $raw);

        if ($lines === false) {
            return null;
        }

        $lineOffsets = $this->lineOffsets($raw, count($lines));

        for ($index = count($lines) - 1; $index >= 0; --$index) {
            $line = $lines[$index];
            $trimmed = ltrim($line);

            if ($trimmed === '') {
                continue;
            }

            if (! in_array($trimmed[0], ['{', '['], true)) {
                continue;
            }

            $offset = $lineOffsets[$index] + strlen($line) - strlen($trimmed);
            $endOffset = $this->jsonEndOffset($raw, $offset);
            $candidate = $endOffset === null
                ? substr($raw, $offset)
                : substr($raw, $offset, $endOffset - $offset);

            try {
                return new JsonOutput(
                    decoded: json_decode($candidate, true, 512, JSON_THROW_ON_ERROR),
                    raw: $candidate,
                    source: $source,
                    line: $index + 1,
                    offset: $offset,
                    clean: false,
                );
            } catch (JsonException) {
                continue;
            }
        }

        return null;
    }

    private function jsonEndOffset(string $raw, int $offset): ?int
    {
        $first = $raw[$offset] ?? null;
        $expectedClosers = match ($first) {
            '{' => ['}'],
            '[' => [']'],
            default => [],
        };

        if ($expectedClosers === []) {
            return null;
        }

        $inString = false;
        $escaped = false;
        $length = strlen($raw);

        for ($index = $offset + 1; $index < $length; ++$index) {
            $char = $raw[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{') {
                $expectedClosers[] = '}';
                continue;
            }

            if ($char === '[') {
                $expectedClosers[] = ']';
                continue;
            }

            $expected = end($expectedClosers);

            if (($char === '}' || $char === ']') && $char === $expected) {
                array_pop($expectedClosers);

                if ($expectedClosers === []) {
                    return $index + 1;
                }
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function lineOffsets(string $raw, int $lineCount): array
    {
        $offsets = [0];
        $length = strlen($raw);

        for ($offset = 0; $offset < $length && count($offsets) < $lineCount; ++$offset) {
            $char = $raw[$offset];

            if ($char !== "\n") {
                continue;
            }

            $offsets[] = $offset + 1;
        }

        while (count($offsets) < $lineCount) {
            $offsets[] = $length;
        }

        return $offsets;
    }

    private function snippet(string $raw): string
    {
        if (strlen($raw) <= self::SNIPPET_LIMIT) {
            return $raw;
        }

        return substr($raw, 0, self::SNIPPET_LIMIT);
    }
}
