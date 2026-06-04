<?php

declare(strict_types=1);

namespace Sift\History;

final readonly class SecretRedactor
{
    private const string REDACTED = '[REDACTED]';

    /**
     * @var list<string>
     */
    private const array SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'set_cookie',
        'api_key',
    ];

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function redactPayload(array $payload): array
    {
        $redacted = $this->redactArray($payload);
        $payload = [];

        foreach ($redacted as $key => $value) {
            if (is_string($key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    public function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->redactArray($value);
        }

        if (is_string($value)) {
            return $this->redactString($value);
        }

        return $value;
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    private function redactArray(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;
                continue;
            }

            $redacted[$key] = $this->redact($value);
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace('-', '_', $key));

        return in_array($normalized, self::SENSITIVE_KEYS, true)
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'passwd');
    }

    private function redactString(string $value): string
    {
        $value = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/=-]{10,}\b/i', 'Bearer ' . self::REDACTED, $value) ?? $value;
        $value = preg_replace('/\bgh[pousr]_\w{20,}\b/', self::REDACTED, $value) ?? $value;

        if ($this->looksLikeFilePath($value)) {
            return $value;
        }

        return preg_replace('/(?<![A-Za-z0-9])[A-Za-z0-9+\/_.=-]{40,}(?![A-Za-z0-9])/', self::REDACTED, $value) ?? $value;
    }

    private function looksLikeFilePath(string $value): bool
    {
        if (! str_contains($value, '/') && ! str_contains($value, '\\')) {
            return false;
        }

        return preg_match('/\.[A-Za-z0-9]{1,8}(?::\d+)?$/', $value) === 1;
    }
}
