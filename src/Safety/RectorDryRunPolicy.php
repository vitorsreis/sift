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
        if ($command->tool() !== 'rector' || $context->repair()) {
            return [];
        }

        $hasDryRun = false;
        $arguments = $command->arguments();

        foreach ($arguments as $index => $argument) {
            if ($argument === '--no-dry-run') {
                return [$this->violation($argument)];
            }

            if (! $this->isDryRunArgument($argument)) {
                continue;
            }

            $value = $this->optionValue($arguments, $index, '--dry-run');

            if ($value === null || in_array($value, ['true', '1'], true)) {
                $hasDryRun = true;

                continue;
            }

            return [$this->violation($argument)];
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
            message: 'Rector execution requires --dry-run unless --repair is explicit.',
            policy: 'rector_dry_run',
            argument: $argument,
        );
    }

    private function isDryRunArgument(string $argument): bool
    {
        return in_array($argument, self::SAFE_DRY_RUN_ARGUMENTS, true)
            || in_array($argument, self::UNSAFE_DRY_RUN_ARGUMENTS, true)
            || $argument === '--dry-run'
            || str_starts_with($argument, '--dry-run=');
    }

    /**
     * @param list<string> $arguments
     */
    private function optionValue(array $arguments, int $index, string $option): ?string
    {
        $argument = $arguments[$index];

        if (str_starts_with($argument, $option . '=')) {
            return strtolower(substr($argument, strlen($option) + 1));
        }

        $next = $arguments[$index + 1] ?? null;

        if (is_string($next) && in_array(strtolower($next), ['true', 'false', '1', '0'], true)) {
            return strtolower($next);
        }

        return null;
    }
}
