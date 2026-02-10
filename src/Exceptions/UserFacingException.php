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

    public static function runNotFound(string $runId): self
    {
        return new self([
            'status' => 'error',
            'error' => [
                'code' => 'run_not_found',
                'message' => sprintf('The run `%s` was not found in history.', $runId),
                'run_id' => $runId,
            ],
        ]);
    }

    public static function parseFailure(string $tool, string $message): self
    {
        return new self([
            'status' => 'error',
            'error' => [
                'code' => 'parse_failure',
                'message' => $message,
                'tool' => $tool,
            ],
        ]);
    }

    public static function configAlreadyExists(string $path): self
    {
        return new self([
            'status' => 'error',
            'error' => [
                'code' => 'config_already_exists',
                'message' => sprintf('The config file `%s` already exists.', $path),
                'path' => $path,
            ],
        ]);
    }

    public static function configNotFound(string $path): self
    {
        return new self([
            'status' => 'error',
            'error' => [
                'code' => 'config_not_found',
                'message' => sprintf('The config file `%s` was not found.', $path),
                'path' => $path,
            ],
        ]);
    }

    public static function invalidConfig(string $path, string $reason): self
    {
        return new self([
            'status' => 'error',
            'error' => [
                'code' => 'invalid_config',
                'message' => sprintf('The config file `%s` is invalid: %s', $path, $reason),
                'path' => $path,
            ],
        ]);
    }
}
