<?php

declare(strict_types=1);

namespace Sift\Console;

/**
 * @phpstan-type ParsedOption bool|int|string|list<bool|int|string>
 */
final readonly class CliRequest
{
    /**
     * @param list<string> $arguments
     * @param array<string, ParsedOption> $options
     * @param array<string, ParsedOption> $globalOptions
     */
    public function __construct(
        private string $command,
        private array $arguments = [],
        private array $options = [],
        private array $globalOptions = [],
    ) {}

    public function command(): string
    {
        return $this->command;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return array<string, ParsedOption>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * @return array<string, ParsedOption>
     */
    public function globalOptions(): array
    {
        return $this->globalOptions;
    }
}
