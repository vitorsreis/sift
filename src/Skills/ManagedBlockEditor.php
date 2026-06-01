<?php

declare(strict_types=1);

namespace Sift\Skills;

final readonly class ManagedBlockEditor
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function upsert(string $contents, string $name, array $metadata, string $body): string
    {
        $block = $this->block($name, $metadata, $body);
        $pattern = $this->pattern($name);

        if (preg_match($pattern, $contents) === 1) {
            return (string) preg_replace($pattern, $block, $contents, 1);
        }

        $prefix = rtrim($contents);

        return ($prefix === '' ? '' : $prefix . PHP_EOL . PHP_EOL) . $block . PHP_EOL;
    }

    /**
     * @return list<SkillManagedMetadata>
     */
    public function metadata(string $contents): array
    {
        $matches = [];

        if (preg_match_all('/<!--\s*sift:skill:(?P<name>[a-z0-9][a-z0-9-]{0,63}):start\s+data="(?P<data>[A-Za-z0-9_-]+)"\s*-->.*?<!--\s*sift:skill:\1:end\s*-->/s', $contents, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $metadata = [];

        foreach ($matches as $match) {
            $payload = $this->decodeMetadata($match['data']);
            $name = $match['name'];
            $item = $payload === null ? null : SkillManagedMetadata::fromPayload($payload, $name);

            if ($item instanceof SkillManagedMetadata) {
                $metadata[] = $item;
            }
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function block(string $name, array $metadata, string $body): string
    {
        return implode(PHP_EOL, [
            sprintf('<!-- sift:skill:%s:start data="%s" -->', $name, $this->encodeMetadata($metadata)),
            rtrim($body),
            sprintf('<!-- sift:skill:%s:end -->', $name),
        ]);
    }

    private function pattern(string $name): string
    {
        $quoted = preg_quote($name, '/');

        return sprintf('/<!--\s*sift:skill:%s:start(?:\s+data="[^"]*")?\s*-->.*?<!--\s*sift:skill:%s:end\s*-->/s', $quoted, $quoted);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function encodeMetadata(array $metadata): string
    {
        return rtrim(strtr(base64_encode(json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }

    /**
     * @return null|array<string, mixed>
     */
    private function decodeMetadata(string $encoded): ?array
    {
        $padded = str_pad(strtr($encoded, '-_', '+/'), (int) ceil(strlen($encoded) / 4) * 4, '=');
        $json = base64_decode($padded, true);

        if (! is_string($json)) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        $payload = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
