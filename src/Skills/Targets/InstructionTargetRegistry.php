<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;

final readonly class InstructionTargetRegistry
{
    public function resolve(string $target): InstructionTarget
    {
        return match ($this->normalize($target)) {
            'generic' => new InstructionFileTarget('generic', 'AGENTS.md'),
            default => throw UserFacingException::withContext(
                errorCode: ErrorCode::UnsupportedTarget,
                message: sprintf('Skill target "%s" is not supported yet.', $target),
                context: ['target' => $target],
            ),
        };
    }

    /**
     * @return list<string>
     */
    public function writeCapableNames(): array
    {
        return ['generic'];
    }

    private function normalize(string $target): string
    {
        return strtolower(trim($target));
    }
}
