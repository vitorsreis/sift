<?php

declare(strict_types=1);

namespace Sift\Console;

/**
 * @phpstan-import-type ParsedOption from CliRequest
 */
final readonly class CommandRoute
{
    /**
     * @param list<string> $arguments
     * @param array<string, ParsedOption> $options
     * @param array<string, ParsedOption> $globalOptions
     */
    public function __construct(
        private string $handler,
        private array $arguments = [],
        private array $options = [],
        private array $globalOptions = [],
    ) {}

    public function handler(): string
    {
        return $this->handler;
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
