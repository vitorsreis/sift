<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Filesystem\Path;

final readonly class ResourcePathResolver
{
    private function __construct(
        private string $projectRoot,
    ) {}

    public static function fromProjectRoot(string $projectRoot): self
    {
        return new self(Path::normalize($projectRoot));
    }

    public static function fromRuntime(): self
    {
        return new self(Path::normalize(dirname(__DIR__, 2)));
    }

    public function resource(string $path): string
    {
        return Path::join($this->projectRoot, 'resources', $path);
    }

    public function skill(string $path): string
    {
        return Path::join($this->projectRoot, 'skills', $path);
    }
}
