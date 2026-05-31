<?php

declare(strict_types=1);

namespace Sift\Core;

use InvalidArgumentException;

final readonly class NormalizedResult
{
    /**
     * @param array<string, mixed> $summary
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $artifacts
     * @param array<string, mixed> $extra
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private string $tool,
        private RunStatus $status,
        private array $summary = [],
        private array $items = [],
        private array $artifacts = [],
        private array $extra = [],
        private array $meta = [],
    ) {
        if (trim($tool) === '') {
            throw new InvalidArgumentException('Normalized result tool cannot be empty.');
        }

        $this->validateItemTypes($items);
    }

    /**
     * @param array<string, mixed> $summary
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $artifacts
     * @param array<string, mixed> $extra
     * @param array<string, mixed> $meta
     */
    public static function passed(
        string $tool,
        array $summary = [],
        array $items = [],
        array $artifacts = [],
        array $extra = [],
        array $meta = [],
    ): self {
        return new self($tool, RunStatus::Passed, $summary, $items, $artifacts, $extra, $meta);
    }

    /**
     * @return array{tool: string, status: string, summary: array<string, mixed>, items: list<array<string, mixed>>, artifacts: list<array<string, mixed>>, extra: array<string, mixed>, meta: array<string, mixed>}
     */
    public function toPayload(): array
    {
        return [
            'tool' => $this->tool,
            'status' => $this->status->value,
            'summary' => $this->summary,
            'items' => $this->items,
            'artifacts' => $this->artifacts,
            'extra' => $this->extra,
            'meta' => $this->meta,
        ];
    }

    /**
     * @param array<string, mixed> $meta
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
            meta: array_replace($this->meta, $meta),
        );
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function validateItemTypes(array $items): void
    {
        foreach ($items as $item) {
            $type = $item['type'] ?? null;

            if (! is_string($type) || ItemType::tryFrom($type) === null) {
                throw new InvalidArgumentException(sprintf('Unknown item type "%s".', is_scalar($type) ? (string) $type : ''));
            }
        }
    }
}
