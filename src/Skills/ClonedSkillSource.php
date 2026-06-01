<?php

declare(strict_types=1);

namespace Sift\Skills;

use Closure;

final readonly class ClonedSkillSource
{
    private Closure $cleanup;

    /**
     * @param callable(): void $cleanup
     */
    public function __construct(
        private SkillSource $source,
        callable $cleanup,
    ) {
        $this->cleanup = Closure::fromCallable($cleanup);
    }

    public function source(): SkillSource
    {
        return $this->source;
    }

    public function cleanup(): void
    {
        ($this->cleanup)();
    }
}
