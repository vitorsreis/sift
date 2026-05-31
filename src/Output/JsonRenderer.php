<?php

declare(strict_types=1);

namespace Sift\Output;

final class JsonRenderer
{
    /**
     * @param array<string, mixed> $payload
     */
    public function render(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}
