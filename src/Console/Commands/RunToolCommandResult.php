<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\OutputPreferences;

final readonly class RunToolCommandResult
{
    /**
     * @param array<string, mixed>|null $payload
     */
    private function __construct(
        private ?array $payload,
        private int $exitCode,
        private OutputPreferences $outputPreferences,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function normalized(array $payload, OutputPreferences $outputPreferences): self
    {
        return new self($payload, 0, $outputPreferences);
    }

    public static function raw(int $exitCode, OutputPreferences $outputPreferences): self
    {
        return new self(null, $exitCode, $outputPreferences);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payload(): ?array
    {
        return $this->payload;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function outputPreferences(): OutputPreferences
    {
        return $this->outputPreferences;
    }
}
