<?php

declare(strict_types=1);

namespace Sift\Tools\Mago;

use Sift\Console\InvalidUsageException;

final readonly class MagoArguments
{
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
    private const array SUPPORTED_SUBCOMMANDS = [
        'lint',
        'analyze',
        'analyse',
        'guard',
        'format',
        'fmt',
    ];

    /**
     * @var list<string>
     */
    private const array UNSUPPORTED_SUBCOMMANDS = [
        'ast',
        'config',
        'init',
        'language-server',
        'list-files',
        'self-update',
    ];

    /**
     * @param list<string> $userArguments
     */
    public function prepare(array $userArguments, bool $machineOutput = true): MagoPreparedArguments
    {
        [$globalArguments, $remaining] = $this->splitGlobalArguments($userArguments);
        [$subcommand, $toolArguments] = $this->splitSubcommand($remaining);

        if ($machineOutput) {
            $globalArguments = $this->withColorsDisabled($globalArguments);
        }

        $toolArguments = $this->withSafeDefaults($subcommand, $toolArguments, $machineOutput);

        return new MagoPreparedArguments(
            subcommand: $subcommand,
            arguments: [...$globalArguments, $subcommand, ...$toolArguments],
        );
    }

    /**
     * @param list<string> $arguments
     */
    public function subcommandFromPrepared(array $arguments): string
    {
        [, $remaining] = $this->splitGlobalArguments($arguments);
        [$subcommand] = $this->splitSubcommand($remaining);

        return $subcommand;
    }

    /**
     * @param list<string> $arguments
     * @return array{list<string>, list<string>}
     */
    private function splitGlobalArguments(array $arguments): array
    {
        $globalArguments = [];
        $index = 0;
        $count = count($arguments);

        while ($index < $count) {
            $argument = $arguments[$index];

            if (! $this->isGlobalOptionWithValue($argument)) {
                break;
            }

            $globalArguments[] = $argument;

            if (! str_contains($argument, '=') && isset($arguments[$index + 1])) {
                $globalArguments[] = $arguments[$index + 1];
                $index += 2;
                continue;
            }

            ++$index;
        }

        return [$globalArguments, array_slice($arguments, $index)];
    }

    /**
     * @param list<string> $arguments
     * @return array{string, list<string>}
     */
    private function splitSubcommand(array $arguments): array
    {
        $first = $arguments[0] ?? null;

        if ($first === null || str_starts_with($first, '-')) {
            return ['lint', $arguments];
        }

        if (in_array($first, self::UNSUPPORTED_SUBCOMMANDS, true)) {
            throw new InvalidUsageException('Mago adapter supports only lint, analyze, guard and format.');
        }

        if (! in_array($first, self::SUPPORTED_SUBCOMMANDS, true)) {
            return ['lint', $arguments];
        }

        return [$this->normalizeSubcommand($first), array_slice($arguments, 1)];
    }

    private function normalizeSubcommand(string $subcommand): string
    {
        return match ($subcommand) {
            'analyse' => 'analyze',
            'fmt' => 'format',
            default => $subcommand,
        };
    }

    /**
     * @param list<string> $globalArguments
     * @return list<string>
     */
    private function withColorsDisabled(array $globalArguments): array
    {
        $remaining = [];
        $skipNext = false;

        foreach ($globalArguments as $argument) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            if ($argument === '--colors') {
                $skipNext = true;
                continue;
            }

            if (str_starts_with($argument, '--colors=')) {
                continue;
            }

            $remaining[] = $argument;
        }

        return [...$remaining, '--colors=never'];
    }

    /**
     * @param list<string> $toolArguments
     * @return list<string>
     */
    private function withSafeDefaults(string $subcommand, array $toolArguments, bool $machineOutput): array
    {
        if ($subcommand === 'format') {
            if (! $this->hasAnyExact($toolArguments, ['--check', '-c', '--dry-run', '-d', '--stdin-input', '-i'])) {
                return ['--check', ...$toolArguments];
            }

            return $toolArguments;
        }

        if ($machineOutput && ! $this->hasOption($toolArguments, '--reporting-format')) {
            return ['--reporting-format=json', ...$toolArguments];
        }

        return $toolArguments;
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
     * @param list<string> $arguments
     */
    private function hasOption(array $arguments, string $option): bool
    {
        foreach ($arguments as $argument) {
            if ($argument === $option || str_starts_with($argument, $option . '=')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $arguments
     * @param list<string> $expected
     */
    private function hasAnyExact(array $arguments, array $expected): bool
    {
        foreach ($arguments as $argument) {
            if (in_array($argument, $expected, true)) {
                return true;
            }
        }

        return false;
    }
}
