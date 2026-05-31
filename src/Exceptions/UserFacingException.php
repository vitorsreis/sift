<?php

declare(strict_types=1);

namespace Sift\Exceptions;

use Sift\Core\ErrorCode;

final class UserFacingException extends SiftException
{
    /**
     * @param array<string, mixed> $context
     */
    public static function withContext(
        ErrorCode $errorCode,
        string $message,
        ?string $hint = null,
        array $context = [],
    ): self {
        return new self($errorCode, $message, $hint, $context);
    }
}
