<?php

declare(strict_types=1);

namespace Sift\Safety;

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Tools\ToolContext;

final readonly class MagoSafeModePolicy implements Policy
{
    /**
     * @var list<string>
     */
    private const array BASELINE_WRITE_ARGUMENTS = [
        '--generate-baseline',
        '--remove-outdated-baseline-entries',
        '--backup-baseline',
    ];

    /**
     * @var list<string>
     */
    private const array GLOBAL_OPTIONS_WITH_VALUES = [
        '--workspace',
        '--config',
        '--php-version',
        '--threads',
        '--colors',
    ];

    /**
     * @var list<string>
     */
    private const array GLOBAL_OPTIONS_WITHOUT_VALUES = [
        '--allow-unsupported-php-version',
        '--no-version-check',
        '--help',
        '-h',
        '--version',
        '-V',
    ];

    /**
     * @var list<string>
     */
    private const array FIX_SUBCOMMANDS = [
        'lint',
        'analyze',
        'guard',
    ];

    /**
     * @var list<string>
     */
    private const array SAFE_DRY_RUN_ARGUMENTS = [
        '--dry-run',
        '--dry-run=true',
        '--dry-run=1',
        '-d',
    ];

    /**
     * @var list<string>
     */
    private const array UNSAFE_DRY_RUN_ARGUMENTS = [
        '--dry-run=false',
        '--dry-run=0',
        '--no-dry-run',
    ];

    /**
     * @var list<string>
     */
    private const array SAFE_FORMAT_ARGUMENTS = [
        '--check',
        '--check=true',
        '--check=1',
        '-c',
        '--dry-run',
        '--dry-run=true',
        '--dry-run=1',
        '-d',
        '--stdin-input',
        '--stdin-input=true',
        '--stdin-input=1',
        '-i',
    ];

    public function violations(PreparedCommand $command, ToolContext $context, ToolConfig $config): array
    {
        if ($command->tool() !== 'mago') {
            return [];
        }

        $arguments = $command->arguments();

        $baselineWriteArgument = $this->firstOption($arguments, self::BASELINE_WRITE_ARGUMENTS);
        if ($baselineWriteArgument !== null) {
            return [$this->violation($baselineWriteArgument, 'Mago baseline write modes are blocked.')];
        }

        $unsafeDryRunArgument = $this->firstExactArgument($arguments, self::UNSAFE_DRY_RUN_ARGUMENTS);
        if ($unsafeDryRunArgument !== null) {
            return [$this->violation($unsafeDryRunArgument, 'Mago safe mode cannot be disabled.')];
        }

        $subcommand = $this->normalizeSubcommand($this->subcommand($arguments));

        if ($subcommand === 'analyze' && $this->hasOption($arguments, '--watch')) {
            return [$this->violation('--watch', 'Mago watch mode is blocked.')];
        }

        if (in_array($subcommand, self::FIX_SUBCOMMANDS, true) && $this->hasOption($arguments, '--fix') && ! $this->hasExactArgument($arguments, self::SAFE_DRY_RUN_ARGUMENTS)) {
            return [$this->violation('--fix', 'Mago fix mode requires --dry-run.')];
        }

        if ($subcommand === 'format' && ! $this->hasExactArgument($arguments, self::SAFE_FORMAT_ARGUMENTS)) {
            return [$this->violation(null, 'Mago format write mode is blocked.')];
        }

        return [];
    }

    /**
     * @param  list<string>  $arguments
     */
    private function subcommand(array $arguments): string
    {
        $index = 0;
        $count = count($arguments);

        while ($index < $count) {
            $argument = $arguments[$index];

            if ($this->isGlobalOptionWithValue($argument)) {
                if (! str_contains($argument, '=')) {
                    ++$index;
                }

                ++$index;

                continue;
            }

            if (in_array($argument, self::GLOBAL_OPTIONS_WITHOUT_VALUES, true)) {
                ++$index;

                continue;
            }

            if ($argument !== '' && ! str_starts_with($argument, '-')) {
                return $argument;
            }

            break;
        }

        return 'lint';
    }

    private function normalizeSubcommand(string $subcommand): string
    {
        return match ($subcommand) {
            'analyse' => 'analyze',
            'fmt' => 'format',
            default => $subcommand,
        };
    }

    private function isGlobalOptionWithValue(string $argument): bool
    {
        foreach (self::GLOBAL_OPTIONS_WITH_VALUES as $option) {
            if ($argument === $option || str_starts_with($argument, $option . '=')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function hasOption(array $arguments, string $option): bool
    {
        return $this->firstOption($arguments, [$option]) !== null;
    }

    /**
     * @param  list<string>  $arguments
     * @param  list<string>  $options
     */
    private function firstOption(array $arguments, array $options): ?string
    {
        foreach ($options as $option) {
            foreach ($arguments as $argument) {
                if ($argument === $option || (str_starts_with($option, '--') && str_starts_with($argument, $option . '='))) {
                    return $argument;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $arguments
     * @param  list<string>  $expected
     */
    private function hasExactArgument(array $arguments, array $expected): bool
    {
        return $this->firstExactArgument($arguments, $expected) !== null;
    }

    /**
     * @param  list<string>  $arguments
     * @param  list<string>  $expected
     */
    private function firstExactArgument(array $arguments, array $expected): ?string
    {
        foreach ($arguments as $argument) {
            if (in_array($argument, $expected, true)) {
                return $argument;
            }
        }

        return null;
    }

    private function violation(?string $argument, string $message): PolicyViolation
    {
        return new PolicyViolation(
            code: ErrorCode::PolicyBlocked,
            message: $message,
            policy: 'mago_safe_mode',
            argument: $argument,
        );
    }
}
