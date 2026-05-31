<?php

declare(strict_types=1);

namespace Sift\Safety;

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Tools\ToolContext;

final readonly class BlockedArgumentsPolicy implements Policy
{
    /**
     * @return PolicyViolation[]
     */
    public function violations(PreparedCommand $command, ToolContext $context, ToolConfig $config): array
    {
        $violations = [];

        foreach ($command->arguments() as $argument) {
            foreach ($config->blockedArgs() as $blockedArgument) {
                if ($argument === $blockedArgument || str_starts_with($argument, $blockedArgument . '=')) {
                    $violations[] = new PolicyViolation(
                        code: ErrorCode::BlockedArgument,
                        message: sprintf('Argument "%s" is blocked for tool "%s".', $argument, $command->tool()),
                        policy: 'blocked_arguments',
                        argument: $argument,
                    );
                }
            }
        }

        return $violations;
    }
}
