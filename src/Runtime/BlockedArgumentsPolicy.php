<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Contracts\PolicyInterface;
use Sift\Contracts\ToolAdapterInterface;
use Sift\Exceptions\UserFacingException;

final class BlockedArgumentsPolicy implements PolicyInterface
{
    public function apply(string $cwd, ToolAdapterInterface $tool, array $arguments, array $toolConfig): void
    {
        foreach ($toolConfig['blockedArgs'] as $blockedArgument) {
            foreach ($arguments as $argument) {
                if ($argument === $blockedArgument || str_starts_with($argument, $blockedArgument.'=')) {
                    throw UserFacingException::blockedArgument($tool->name(), $blockedArgument);
                }
            }
        }
    }
}
