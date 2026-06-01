<?php

declare(strict_types=1);

namespace Sift\Skills;

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;

final readonly class SkillSourcePolicy
{
    public function assertAllowed(string $source): void
    {
        $source = trim($source);

        if (preg_match('#^http://#i', $source) === 1) {
            $this->reject($source, 'HTTP skill sources are not allowed.');
        }

        if (preg_match('#^(?:ssh|git)://#i', $source) === 1 || str_starts_with($source, 'git@')) {
            $this->reject($source, 'SSH and Git protocol skill sources are not allowed.');
        }

        if (preg_match('#^https://[^/]*@#i', $source) === 1) {
            $this->reject($source, 'Skill source URLs must not include credentials.');
        }

        if (preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $source) === 1) {
            $this->reject($source, 'Skill sources must not contain path traversal segments.');
        }
    }

    private function reject(string $source, string $message): never
    {
        throw UserFacingException::withContext(
            errorCode: ErrorCode::PolicyBlocked,
            message: $message,
            context: [
                'source' => $source,
                'policy' => 'skill_source',
            ],
        );
    }
}
