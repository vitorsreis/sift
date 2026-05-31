<?php

declare(strict_types=1);

namespace Sift\Tools;

use UnexpectedValueException;

final readonly class JsonOutput
{
    public function __construct(
        private mixed $decoded,
        private string $raw,
        private string $source,
        private ?int $line = null,
        private ?int $offset = null,
        private bool $clean = true,
    ) {}

    public function decoded(): mixed
    {
        return $this->decoded;
    }

    /**
     * @return array<string, mixed>
     */
    public function object(): array
    {
        if (! is_array($this->decoded) || array_is_list($this->decoded)) {
            throw new UnexpectedValueException('Decoded JSON root must be an object.');
        }

        $object = [];

        foreach ($this->decoded as $key => $value) {
            if (! is_string($key)) {
                throw new UnexpectedValueException('Decoded JSON root must be an object.');
            }

            $object[$key] = $value;
        }

        return $object;
    }

    public function raw(): string
    {
        return $this->raw;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function line(): ?int
    {
        return $this->line;
    }

    public function offset(): ?int
    {
        return $this->offset;
    }

    public function clean(): bool
    {
        return $this->clean;
    }
}
