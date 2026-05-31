<?php

declare(strict_types=1);

namespace Sift\Safety;

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Tools\ToolContext;

final readonly class RectorDryRunPolicy implements Policy
{
    /**
     * @var list<string>
     */
    private const array SAFE_DRY_RUN_ARGUMENTS = [
        '--dry-run',
        '--dry-run=true',
        '--dry-run=1',
    ];

    /**
     * @var list<string>
     */
    private const array UNSAFE_DRY_RUN_ARGUMENTS = [
        '--dry-run=false',
        '--dry-run=0',
        '--no-dry-run',
    ];

    public function violations(PreparedCommand $command, ToolContext $context, ToolConfig $config): array
    {
        if ($command->tool() !== 'rector') {
            return [];
        }

        $hasDryRun = false;

        foreach ($command->arguments() as $argument) {
            if (in_array($argument, self::UNSAFE_DRY_RUN_ARGUMENTS, true)) {
                return [$this->violation($argument)];
            }

            if (in_array($argument, self::SAFE_DRY_RUN_ARGUMENTS, true)) {
                $hasDryRun = true;
            }
        }

        if ($hasDryRun) {
            return [];
        }

        return [$this->violation(null)];
    }

    private function violation(?string $argument): PolicyViolation
    {
        return new PolicyViolation(
            code: ErrorCode::PolicyBlocked,
            message: 'Rector execution requires --dry-run.',
            policy: 'rector_dry_run',
            argument: $argument,
        );
    }
}
