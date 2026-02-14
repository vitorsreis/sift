<?php

declare(strict_types=1);

namespace Sift\Renderers;

use Sift\Contracts\RendererInterface;

final class MarkdownRenderer implements RendererInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload, bool $pretty = false): string
    {
        $lines = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $lines[] = sprintf('**%s**', $this->label($key));

                if ($value === []) {
                    $lines[] = '- empty';

                    continue;
                }

                foreach ($value as $itemKey => $itemValue) {
                    $lines[] = $this->renderArrayEntry($itemKey, $itemValue);
                }

                continue;
            }

            $lines[] = sprintf('- **%s:** %s', $this->label($key), $this->scalar($value));
        }

        return implode(PHP_EOL, $lines);
    }

    private function renderArrayEntry(int|string $key, mixed $value): string
    {
        if (is_array($value)) {
            return sprintf(
                '- `%s`: `%s`',
                $key,
                json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            );
        }

        if (is_int($key)) {
            return sprintf('- %s', $this->scalar($value));
        }

        return sprintf('- **%s:** %s', $this->label($key), $this->scalar($value));
    }

    private function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }

    private function label(string $key): string
    {
        return str_replace('_', ' ', $key);
    }
}
