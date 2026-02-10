<?php

declare(strict_types=1);

namespace Sift\Contracts;

interface RendererInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload, bool $pretty = false): string;
}
