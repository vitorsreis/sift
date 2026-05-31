<?php

declare(strict_types=1);

namespace Sift\Tools;

use InvalidArgumentException;
use Sift\Console\CliRequest;
use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;

/**
 * @phpstan-import-type ParsedOption from CliRequest
 */
final readonly class CliArguments
{
    /**
     * @param list<string> $toolArguments
     * @param array<string, ParsedOption> $siftOptions
     */
    public function __construct(
        private string $tool,
        private array $toolArguments = [],
        private array $siftOptions = [],
    ) {
        if (trim($tool) === '') {
            throw new InvalidArgumentException('Tool name cannot be empty.');
        }
    }

    public static function fromRoute(CommandRoute $route): self
    {
        $arguments = $route->arguments();
        $tool = array_shift($arguments);

        if ($tool === null) {
            throw new InvalidUsageException('Run command requires a tool name.');
        }

        return new self(
            tool: $tool,
            toolArguments: $arguments,
            siftOptions: [...$route->globalOptions(), ...$route->options()],
        );
    }

    public function tool(): string
    {
        return $this->tool;
    }

    /**
     * @return list<string>
     */
    public function toolArguments(): array
    {
        return $this->toolArguments;
    }

    /**
     * @return ParsedOption|null
     */
    public function siftOption(string $name): bool|int|string|array|null
    {
        return $this->siftOptions[$name] ?? null;
    }

    public function has(string $argument): bool
    {
        foreach ($this->toolArguments as $toolArgument) {
            if ($this->matchesArgument($toolArgument, $argument)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $arguments
     */
    public function hasAny(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if ($this->has($argument)) {
                return true;
            }
        }

        return false;
    }

    public function value(string $argument): ?string
    {
        foreach ($this->toolArguments as $index => $toolArgument) {
            if (str_starts_with($toolArgument, $argument . '=')) {
                return substr($toolArgument, strlen($argument) + 1);
            }

            if ($toolArgument !== $argument) {
                continue;
            }

            $next = $this->toolArguments[$index + 1] ?? null;

            if ($next === null || ! $this->isValueToken($next)) {
                return null;
            }

            return $next;
        }

        return null;
    }

    public function requiredValue(string $argument): string
    {
        $value = $this->value($argument);

        if ($value === null || $value === '') {
            throw new InvalidUsageException(sprintf('Argument "%s" requires a value.', $argument));
        }

        return $value;
    }

    public function floatValue(string $argument): float
    {
        $value = $this->requiredValue($argument);

        if (preg_match('/^-?(?:\d+(?:\.\d+)?|\.\d+)$/', $value) !== 1) {
            throw new InvalidUsageException(sprintf('Argument "%s" expects a numeric value.', $argument));
        }

        return (float) $value;
    }

    private function matchesArgument(string $toolArgument, string $argument): bool
    {
        return $toolArgument === $argument || str_starts_with($toolArgument, $argument . '=');
    }

    private function isValueToken(string $token): bool
    {
        return ! str_starts_with($token, '-') || preg_match('/^-?(?:\d+(?:\.\d+)?|\.\d+)$/', $token) === 1;
    }
}
