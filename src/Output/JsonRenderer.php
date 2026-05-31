<?php

declare(strict_types=1);

namespace Sift\Output;

use Sift\Console\OutputPreferences;

final readonly class JsonRenderer
{
    public function __construct(
        private PayloadSizer $payloadSizer = new PayloadSizer(),
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function render(array $payload, ?OutputPreferences $preferences = null): string
    {
        if ($preferences instanceof OutputPreferences) {
            $payload = $this->payloadSizer->resize($payload, $preferences);
        }

        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES;

        if ($preferences?->pretty() === true) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($payload, $flags) . PHP_EOL;
    }
}
