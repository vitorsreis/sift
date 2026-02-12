<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Contracts\PolicyInterface;
use Sift\Contracts\ToolAdapterInterface;
use Sift\Exceptions\UserFacingException;

final class ToolEnabledPolicy implements PolicyInterface
{
    public function apply(string $cwd, ToolAdapterInterface $tool, array $arguments, array $toolConfig): void
    {
        if ($toolConfig['enabled'] !== true) {
            throw UserFacingException::toolDisabled($tool->name());
        }
    }
}
