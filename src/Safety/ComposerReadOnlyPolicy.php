<?php

declare(strict_types=1);

namespace Sift\Safety;

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Tools\ToolContext;

final readonly class ComposerReadOnlyPolicy implements Policy
{
    /**
     * @var list<string>
     */
    private const array ALLOWED_SUBCOMMANDS = [
        'audit',
        'licenses',
        'outdated',
        'show',
        'validate',
    ];

    public function violations(PreparedCommand $command, ToolContext $context, ToolConfig $config): array
    {
        if ($command->tool() !== 'composer') {
            return [];
        }

        $subcommand = $command->arguments()[0] ?? null;

        if (is_string($subcommand) && in_array($subcommand, self::ALLOWED_SUBCOMMANDS, true)) {
            return [];
        }

        return [
            new PolicyViolation(
                code: ErrorCode::PolicyBlocked,
                message: 'Composer execution is restricted to read-only subcommands.',
                policy: 'composer_read_only',
                argument: $subcommand,
            ),
        ];
    }
}
