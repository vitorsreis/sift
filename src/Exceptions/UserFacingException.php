<?php

declare(strict_types=1);

namespace Sift\Exceptions;

use RuntimeException;

final class UserFacingException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly array $payload,
        private readonly int $processExitCode = 1,
    ) {
        parent::__construct((string) ($payload['message'] ?? 'User-facing exception.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function processExitCode(): int
    {
        return $this->processExitCode;
    }

    public static function invalidUsage(string $message): self
    {
        return new self([
            'status' => 'error',
            'error' => [
                'code' => 'invalid_usage',
                'message' => $message,
            ],
        ]);
    }

    public static function unsupportedTool(string $tool): self
    {
        return new self([
            'status' => 'error',
            'error' => [
                'code' => 'unsupported_tool',
                'message' => sprintf('The tool `%s` is not supported by Sift.', $tool),
                'tool' => $tool,
            ],
        ]);
    }

    public static function toolNotInstalled(string $tool, string $hint): self
    {
        return new self([
            'status' => 'error',
            'error' => [
                'code' => 'tool_not_installed',
                'message' => sprintf('The tool `%s` is not installed in this project.', $tool),
                'tool' => $tool,
                'hint' => $hint,
            ],
        ]);
    }
}
