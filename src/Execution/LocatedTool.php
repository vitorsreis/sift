<?php

declare(strict_types=1);

namespace Sift\Execution;

use InvalidArgumentException;

final readonly class LocatedTool
{
    public function __construct(
        private string $tool,
        private string $binary,
        private string $candidate,
        private string $source,
    ) {
        if (trim($tool) === '') {
            throw new InvalidArgumentException('Located tool name cannot be empty.');
        }

        if (trim($binary) === '') {
            throw new InvalidArgumentException('Located tool binary cannot be empty.');
        }
    }

    public function tool(): string
    {
        return $this->tool;
    }

    public function binary(): string
    {
        return $this->binary;
    }

    public function candidate(): string
    {
        return $this->candidate;
    }

    public function source(): string
    {
        return $this->source;
    }
}
