<?php

declare(strict_types=1);

namespace Sift\Skills;

use InvalidArgumentException;

final readonly class Skill
{
    public function __construct(
        private string $name,
        private string $description,
        private string $path,
        private string $skillFile,
        private string $source,
        private string $sourceType,
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $name) !== 1) {
            throw new InvalidArgumentException('Skill name must be a lowercase slug.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function skillFile(): string
    {
        return $this->skillFile;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function sourceType(): string
    {
        return $this->sourceType;
    }
}
