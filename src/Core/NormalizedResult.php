<?php

declare(strict_types=1);

namespace Sift\Core;

use InvalidArgumentException;

final readonly class NormalizedResult
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $artifacts
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $tool,
        public string $status,
        public array $summary = [],
        public array $items = [],
        public array $artifacts = [],
        public array $extra = [],
        public array $meta = [],
    ) {
        if ($this->tool === '') {
            throw new InvalidArgumentException('NormalizedResult tool must not be empty.');
        }

        if ($this->status === '') {
            throw new InvalidArgumentException('NormalizedResult status must not be empty.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tool' => $this->tool,
            'status' => $this->status,
            'summary' => $this->summary,
            'items' => $this->items,
            'artifacts' => $this->artifacts,
            'extra' => $this->extra,
            'meta' => $this->meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        return new self(
            tool: $this->tool,
            status: $this->status,
            summary: $this->summary,
            items: $this->items,
            artifacts: $this->artifacts,
            extra: $this->extra,
            meta: $meta,
        );
    }
}
