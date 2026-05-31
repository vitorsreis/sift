<?php

declare(strict_types=1);

namespace Sift\Output;

use Sift\Config\ConfigValidationException;
use Sift\Console\InvalidUsageException;
use Sift\Core\ErrorCode;
use Sift\Core\RunStatus;
use Sift\Exceptions\UserFacingException;

final class ErrorPayload
{
    /**
     * @param array<string, mixed> $context
     *
     * @return array{status: string, error: array<string, mixed>}
     */
    public static function make(
        ErrorCode|string $errorCode,
        string $message,
        ?string $hint = null,
        array $context = [],
    ): array {
        $error = [
            'code' => self::errorCodeValue($errorCode),
            'message' => $message,
        ];

        if ($hint !== null) {
            $error['hint'] = $hint;
        }

        foreach ($context as $key => $value) {
            if ($value !== null) {
                $error[$key] = $value;
            }
        }

        return [
            'status' => RunStatus::Error->value,
            'error' => $error,
        ];
    }

    /**
     * @return array{status: string, error: array<string, mixed>}
     */
    public static function fromInvalidUsage(InvalidUsageException $exception): array
    {
        return self::make(
            errorCode: ErrorCode::InvalidUsage,
            message: $exception->getMessage(),
            hint: 'Run "sift help" to list available commands.',
        );
    }

    /**
     * @return array{status: string, error: array<string, mixed>}
     */
    public static function fromConfigValidation(ConfigValidationException $exception): array
    {
        return self::make(
            errorCode: $exception->errorCode(),
            message: $exception->getMessage(),
            context: ['path' => $exception->path()],
        );
    }

    /**
     * @return array{status: string, error: array<string, mixed>}
     */
    public static function fromUserFacing(UserFacingException $exception): array
    {
        return self::make(
            errorCode: $exception->errorCode(),
            message: $exception->getMessage(),
            hint: $exception->hint(),
            context: $exception->context(),
        );
    }

    private static function errorCodeValue(ErrorCode|string $errorCode): string
    {
        return $errorCode instanceof ErrorCode ? $errorCode->value : $errorCode;
    }
}
