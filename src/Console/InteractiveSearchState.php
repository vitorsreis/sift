<?php

declare(strict_types=1);

namespace Sift\Console;

final class InteractiveSearchState
{
    public string $query = '';

    /** @var list<array<string, mixed>> */
    public array $results = [];

    public int $selected = 0;

    public int $lastRenderedLines = 0;

    public ?string $lastSearchedQuery = null;

    public ?string $pendingQuery = null;

    public ?float $debounceUntil = null;

    public bool $loading = false;

    /** @var array<string, list<array<string, mixed>>> */
    public array $cache = [];

    public function hasPendingSearch(): bool
    {
        return $this->loading || $this->pendingQuery !== null;
    }
}
