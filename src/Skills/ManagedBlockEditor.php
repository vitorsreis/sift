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
}
