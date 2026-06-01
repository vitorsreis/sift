<?php

declare(strict_types=1);

namespace Sift\History;

interface RunStore
{
    /**
     * @param array<string, mixed> $document
     */
    public function store(array $document): void;

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $runId): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array;

    public function remove(string $runId): bool;

    public function clearAll(): int;
}
