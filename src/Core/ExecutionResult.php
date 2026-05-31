<?php

declare(strict_types=1);

namespace Sift\Core;

final readonly class ExecutionResult
{
    private function __construct(
        private int $exitCode,
        private string $stdout,
        private string $stderr,
        private float $durationSeconds,
        private bool $timedOut,
        private bool $interrupted,
        private ?ErrorCode $errorCode,
    ) {}

    public static function completed(
        int $exitCode,
        string $stdout,
        string $stderr,
        float $durationSeconds,
    ): self {
        return new self(
            exitCode: $exitCode,
            stdout: $stdout,
            stderr: $stderr,
            durationSeconds: $durationSeconds,
            timedOut: false,
            interrupted: false,
            errorCode: null,
        );
    }

    public static function timeout(
        string $stdout,
        string $stderr,
        float $durationSeconds,
    ): self {
        return new self(
            exitCode: 2,
            stdout: $stdout,
            stderr: $stderr,
            durationSeconds: $durationSeconds,
            timedOut: true,
            interrupted: false,
            errorCode: ErrorCode::ProcessTimeout,
        );
    }

    public static function interruption(
        string $stdout,
        string $stderr,
        float $durationSeconds,
    ): self {
        return new self(
            exitCode: 130,
            stdout: $stdout,
            stderr: $stderr,
            durationSeconds: $durationSeconds,
            timedOut: false,
            interrupted: true,
            errorCode: ErrorCode::ProcessInterrupted,
        );
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function stdout(): string
    {
        return $this->stdout;
    }

    public function stderr(): string
    {
        return $this->stderr;
    }

    public function durationSeconds(): float
    {
        return $this->durationSeconds;
    }

    public function successful(): bool
    {
        return $this->exitCode === 0 && ! $this->timedOut && ! $this->interrupted;
    }

    public function timedOut(): bool
    {
        return $this->timedOut;
    }

    public function interrupted(): bool
    {
        return $this->interrupted;
    }

    public function errorCode(): ?ErrorCode
    {
        return $this->errorCode;
    }
}
