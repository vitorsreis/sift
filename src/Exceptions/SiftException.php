<?php

declare(strict_types=1);

namespace Sift\Exceptions;

use RuntimeException;
use Sift\Core\ErrorCode;
use Throwable;

class SiftException extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly ErrorCode $errorCode,
        string $message,
        private readonly ?string $hint = null,
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    public function hint(): ?string
    {
        return $this->hint;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
