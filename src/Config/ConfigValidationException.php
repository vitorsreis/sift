<?php

declare(strict_types=1);

namespace Sift\Config;

use RuntimeException;
use Sift\Core\ErrorCode;

final class ConfigValidationException extends RuntimeException
{
    private function __construct(
        private readonly ErrorCode $errorCode,
        private readonly ?string $path,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalidConfig(?string $path, string $message): self
    {
        return new self(ErrorCode::InvalidConfig, $path, $message);
    }

    public static function schemaUnsupported(?string $path, string $message): self
    {
        return new self(ErrorCode::ConfigSchemaUnsupported, $path, $message);
    }

    public function errorCode(): string
    {
        return $this->errorCode->value;
    }

    public function path(): ?string
    {
        return $this->path;
    }
}
