<?php

declare(strict_types=1);

namespace Sift\Tools\Mago;

final readonly class MagoPreparedArguments
{
    /**
     * @param list<string> $arguments
     */
    public function __construct(
        private string $subcommand,
        private array $arguments,
    ) {}

    public function subcommand(): string
    {
        return $this->subcommand;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }
}
