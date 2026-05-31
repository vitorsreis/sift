<?php

declare(strict_types=1);

namespace Sift\Safety;

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Tools\ToolContext;

final readonly class ToolEnabledPolicy implements Policy
{
    public function violations(PreparedCommand $command, ToolContext $context, ToolConfig $config): array
    {
        if ($config->enabled()) {
            return [];
        }

        return [
            new PolicyViolation(
                code: ErrorCode::ToolDisabled,
                message: sprintf('Tool "%s" is disabled.', $command->tool()),
                policy: 'tool_enabled',
            ),
        ];
    }
}
