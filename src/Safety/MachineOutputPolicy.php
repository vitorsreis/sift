<?php

declare(strict_types=1);

namespace Sift\Safety;

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Tools\ToolContext;

final readonly class MachineOutputPolicy implements Policy
{
    /**
     * @var list<string>
     */
    private const array MACHINE_FORMAT_OPTIONS = [
        '--error-format',
        '--report',
        '--output-format',
        '--reporting-format',
        '--format',
    ];

    public function violations(PreparedCommand $command, ToolContext $context, ToolConfig $config): array
    {
        if ($context->raw()) {
            return [];
        }

        $blockedArgument = $this->firstNonJsonFormatArgument($command->arguments());

        if ($blockedArgument === null) {
            return [];
        }

        return [
            new PolicyViolation(
                code: ErrorCode::PolicyBlocked,
                message: 'Native non-JSON output formats are blocked outside raw mode.',
                policy: 'machine_output',
                argument: $blockedArgument,
            ),
        ];
    }

    /**
     * @param  list<string>  $arguments
     */
    private function firstNonJsonFormatArgument(array $arguments): ?string
    {
        foreach ($arguments as $index => $argument) {
            foreach (self::MACHINE_FORMAT_OPTIONS as $option) {
                if ($argument === $option) {
                    $value = $arguments[$index + 1] ?? null;
                    if ($value === null) {
                        continue;
                    }

                    if (str_starts_with((string) $value, '-')) {
                        continue;
                    }

                    if (! $this->isJsonValue($value)) {
                        return $option . '=' . $value;
                    }
                }

                if (str_starts_with($argument, $option . '=')) {
                    $value = substr($argument, strlen($option) + 1);

                    if (! $this->isJsonValue($value)) {
                        return $argument;
                    }
                }
            }
        }

        return null;
    }

    private function isJsonValue(string $value): bool
    {
        return strtolower($value) === 'json';
    }
}
