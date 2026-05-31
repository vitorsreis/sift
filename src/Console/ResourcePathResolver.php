<?php

declare(strict_types=1);

namespace Sift\Console;

final class ResourcePathResolver
{
    private function __construct(
        private readonly string $projectRoot,
    ) {}

    public static function fromProjectRoot(string $projectRoot): self
    {
        return new self(rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $projectRoot), DIRECTORY_SEPARATOR));
    }

    public function resource(string $path): string
    {
        return $this->join($this->projectRoot, 'resources', $path);
    }

    public function skill(string $path): string
    {
        return $this->join($this->projectRoot, 'skills', $path);
    }

    private function join(string ...$parts): string
    {
        return implode(DIRECTORY_SEPARATOR, array_map(
            static fn(string $part): string => trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part), DIRECTORY_SEPARATOR),
            $parts,
        ));
    }
}
