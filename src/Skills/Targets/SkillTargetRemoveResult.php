<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

final readonly class SkillTargetRemoveResult
{
    public function __construct(
        private string $skillName,
        private string $target,
        private string $path,
        private string $action,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toItem(): array
    {
        return [
            'name' => $this->skillName,
            'target' => $this->target,
            'path' => $this->path,
            'action' => $this->action,
        ];
    }
}
