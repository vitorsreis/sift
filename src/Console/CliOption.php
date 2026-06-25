<?php

declare(strict_types=1);

namespace Sift\Console;

final readonly class CliOption
{
    private function __construct(
        private string $name,
        private CliOptionType $type,
        private ?string $shortAlias = null,
        private bool $repeatable = false,
        private bool $variadic = false,
    ) {}

    public static function boolean(string $name, ?string $shortAlias = null, bool $repeatable = false): self
    {
        return new self($name, CliOptionType::Boolean, $shortAlias, $repeatable);
    }

    public static function string(string $name, ?string $shortAlias = null, bool $repeatable = false, bool $variadic = false): self
    {
        return new self($name, CliOptionType::String, $shortAlias, $repeatable, $variadic);
    }

    public static function integer(string $name, ?string $shortAlias = null, bool $repeatable = false): self
    {
        return new self($name, CliOptionType::Integer, $shortAlias, $repeatable);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): CliOptionType
    {
        return $this->type;
    }

    public function shortAlias(): ?string
    {
        return $this->shortAlias;
    }

    public function repeatable(): bool
    {
        return $this->repeatable;
    }

    public function variadic(): bool
    {
        return $this->variadic;
    }
}
