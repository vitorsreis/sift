<?php

declare(strict_types=1);

namespace Sift\History;

use Closure;
use Sift\Config\HistoryConfig;
use Sift\Core\Clock;
use Sift\Core\SystemClock;

final readonly class RunHistoryService
{
    /**
     * @var Closure(HistoryConfig): RunStore
     */
    private Closure $storeFactory;

    /**
     * @param (callable(HistoryConfig): RunStore)|null $storeFactory
     */
    public function __construct(
        ?callable $storeFactory = null,
        private RunIdGenerator $runIdGenerator = new RunIdGenerator(),
        private SecretRedactor $redactor = new SecretRedactor(),
        private HistoryRetentionPolicy $retentionPolicy = new HistoryRetentionPolicy(),
        private Clock $clock = new SystemClock(),
    ) {
        $this->storeFactory = Closure::fromCallable(
            $storeFactory ?? static fn(HistoryConfig $config): RunStore => new FileRunStore($config->path()),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    public function record(array $payload, HistoryConfig $config): ?array
    {
        if (! $config->enabled()) {
            return null;
        }

        $storedAt = $this->clock->now()->format(DATE_ATOM);
        $redactedPayload = $config->redactSecrets() ? $this->redactor->redactPayload($payload) : $payload;
        $document = $this->truncate($this->document($redactedPayload, $storedAt), $config->maxBytesPerRun());
        $store = ($this->storeFactory)($config);

        $store->store($document);
        $this->applyRetention($store, $config);

        return $document;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function document(array $payload, string $storedAt): array
    {
        $tool = $this->stringValue($payload['tool'] ?? null, 'unknown');
        $status = $this->stringValue($payload['status'] ?? null, 'error');
        $summary = $this->objectValue($payload['summary'] ?? []);
        $createdAt = $this->createdAt($payload, $storedAt);

        return [
            'run_id' => $this->runIdGenerator->generate(),
            'stored_at' => $storedAt,
            'created_at' => $createdAt,
            'tool' => $tool,
            'status' => $status,
            'summary' => $summary,
            'payload' => $payload,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createdAt(array $payload, string $fallback): string
    {
        $meta = $payload['meta'] ?? null;

        if (! is_array($meta) || array_is_list($meta)) {
            return $fallback;
        }

        $createdAt = $meta['created_at'] ?? null;

        return is_string($createdAt) && $createdAt !== '' ? $createdAt : $fallback;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     */
    private function truncate(array $document, int $maxBytes): array
    {
        $originalBytes = $this->bytes($document);

        if ($originalBytes <= $maxBytes) {
            return $document;
        }

        $payload = $this->objectValue($document['payload'] ?? []);
        $meta = $this->objectValue($payload['meta'] ?? []);
        $payload['items'] = [];
        $payload['artifacts'] = [];
        $payload['extra'] = [];
        $payload['meta'] = [
            ...$meta,
            'truncated' => true,
            'original_bytes' => $originalBytes,
            'max_bytes' => $maxBytes,
        ];
        $document['payload'] = $payload;

        if ($this->bytes($document) <= $maxBytes) {
            return $document;
        }

        $document['payload'] = [
            'tool' => $document['tool'],
            'status' => $document['status'],
            'summary' => $document['summary'],
            'items' => [],
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'created_at' => $document['created_at'],
                'truncated' => true,
                'original_bytes' => $originalBytes,
                'max_bytes' => $maxBytes,
            ],
        ];

        return $document;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function bytes(array $document): int
    {
        return strlen(json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function applyRetention(RunStore $store, HistoryConfig $config): void
    {
        foreach ($this->retentionPolicy->expiredRunIds($store->list(), $config, $this->clock->now()) as $runId) {
            $store->remove($runId);
        }
    }

    private function stringValue(mixed $value, string $fallback): string
    {
        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectValue(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value)) {
            return [];
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $object[$key] = $item;
            }
        }

        return $object;
    }
}
