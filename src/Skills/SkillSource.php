<?php

declare(strict_types=1);

namespace Sift\Skills;

final readonly class SkillSource
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        private string $source,
        private string $type,
        private ?string $path = null,
        private ?string $repositoryUrl = null,
        private array $warnings = [],
        private ?string $resolvedRef = null,
    ) {}

    public function source(): string
    {
        return $this->source;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function repositoryUrl(): ?string
    {
        return $this->repositoryUrl;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function resolvedRef(): ?string
    {
        return $this->resolvedRef;
    }

    public function withPath(string $path, ?string $resolvedRef = null): self
    {
        return new self(
            source: $this->source,
            type: $this->type,
            path: $path,
            repositoryUrl: $this->repositoryUrl,
            warnings: $this->warnings,
            resolvedRef: $resolvedRef ?? $this->resolvedRef,
        );
    }
}
