<?php

declare(strict_types=1);

namespace Sift\Output;

use Sift\Console\OutputPreferences;

final class JsonRenderer
{
    /**
     * @param array<string, mixed> $payload
     */
    public function render(array $payload, ?OutputPreferences $preferences = null): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES;

        if ($preferences?->pretty() === true) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($payload, $flags) . PHP_EOL;
    }
}
