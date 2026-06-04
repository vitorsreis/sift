<?php

declare(strict_types=1);

namespace Sift\History;

final readonly class RunIdGenerator
{
    public function generateFullId(string $tool): string
    {
        $core = $this->generateUniqueId();

        return 'sift_' . $core . '_' . RunIdFormat::toolSlug($tool);
    }

    public function generateUniqueId(): string
    {
        return $this->base36((string) time(), 10, 7)
            . $this->base36(bin2hex(random_bytes(4)), 16, 7);
    }

    private function base36(string $value, int $fromBase, int $width): string
    {
        return str_pad(base_convert($value, $fromBase, 36), $width, '0', STR_PAD_LEFT);
    }
}
