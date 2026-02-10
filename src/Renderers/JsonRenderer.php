<?php

declare(strict_types=1);

namespace Sift\Renderers;

use Sift\Contracts\RendererInterface;

final class JsonRenderer implements RendererInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($payload, $flags);
    }
}
