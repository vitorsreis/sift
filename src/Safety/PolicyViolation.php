<?php

declare(strict_types=1);

namespace Sift\Safety;

use Sift\Core\ErrorCode;

final readonly class PolicyViolation
{
    public function __construct(
        private ErrorCode $code,
        private string $message,
        private string $policy,
        private ?string $argument = null,
    ) {}

    public function code(): ErrorCode
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function policy(): string
    {
        return $this->policy;
    }

    public function argument(): ?string
    {
        return $this->argument;
    }

    /**
     * @return array{code: string, message: string, argument: ?string, policy: string}
     */
    public function toPayload(): array
    {
        return [
            'code' => $this->code->value,
            'message' => $this->message,
            'argument' => $this->argument,
            'policy' => $this->policy,
        ];
    }
}
