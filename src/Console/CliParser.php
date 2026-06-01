<?php

declare(strict_types=1);

namespace Sift\Console;

/**
 * @phpstan-import-type ParsedOption from CliRequest
 */
final readonly class CliParser
{
    /**
     * @param array<string, CliOption> $globalOptions
     * @param array<string, array<string, CliOption>> $commandOptions
     */
    private function __construct(
        private array $globalOptions,
        private array $commandOptions,
    ) {}

    public static function forSift(): self
    {
        $globalOptions = self::optionMap([
            CliOption::boolean('compact'),
            CliOption::boolean('full'),
            CliOption::boolean('pretty', 'p'),
            CliOption::boolean('no-pretty', 'P'),
            CliOption::boolean('raw'),
            CliOption::boolean('show-process'),
            CliOption::boolean('no-show-process'),
            CliOption::boolean('debug'),
            CliOption::boolean('history'),
            CliOption::boolean('no-history'),
            CliOption::string('config', 'c'),
            CliOption::string('d', repeatable: true),
        ]);

        $configOptions = self::optionMap([CliOption::string('config', 'c')]);
        $historyOptions = self::optionMap([
            CliOption::integer('limit', 'l'),
            CliOption::integer('offset', 'o'),
            CliOption::string('config', 'c'),
        ]);
        $skillsScopedOptions = [
            CliOption::boolean('global', 'g'),
            CliOption::string('agent', 'a'),
            CliOption::string('skill', 's', repeatable: true),
        ];

        return new self($globalOptions, [
            'init' => self::optionMap([
                CliOption::boolean('force', 'f'),
                CliOption::boolean('yes', 'y'),
                CliOption::boolean('skill'),
                CliOption::boolean('no-skill'),
                CliOption::string('config', 'c'),
            ]),
            'validate' => $configOptions,
            'tools list' => $configOptions,
            'skills add' => self::optionMap([
                ...$skillsScopedOptions,
                CliOption::boolean('list', 'l'),
                CliOption::boolean('yes', 'y'),
                CliOption::boolean('all'),
                CliOption::string('config', 'c'),
            ]),
            'skills list' => self::optionMap($skillsScopedOptions),
            'skills remove' => self::optionMap([
                ...$skillsScopedOptions,
                CliOption::boolean('yes', 'y'),
                CliOption::boolean('all'),
            ]),
            'skills update' => self::optionMap([
                ...$skillsScopedOptions,
                CliOption::boolean('yes', 'y'),
                CliOption::boolean('all'),
            ]),
            'skills find' => [],
            'skills init' => self::optionMap([CliOption::boolean('yes', 'y')]),
            'history list' => $historyOptions,
            'history view' => $historyOptions,
            'history clear' => $historyOptions,
            'history remove' => $historyOptions,
        ]);
    }

    /**
     * @param list<string> $tokens
     */
    public function parse(array $tokens): CliRequest
    {
        [$globalOptions, $remainingTokens] = $this->parseGlobalOptions($tokens);

        if ($remainingTokens === []) {
            return new CliRequest('help', globalOptions: $globalOptions);
        }

        [$command, $arguments] = $this->resolveCommand($remainingTokens);

        if ($command === 'run') {
            return new CliRequest(
                command: 'run',
                arguments: $this->stripEndOfOptionsMarker($arguments),
                globalOptions: $globalOptions,
            );
        }

        [$options, $positionalArguments] = $this->parseCommandOptions($command, $arguments);

        return new CliRequest(
            command: $command,
            arguments: $positionalArguments,
            options: $options,
            globalOptions: $globalOptions,
        );
    }

    /**
     * @param list<CliOption> $options
     *
     * @return array<string, CliOption>
     */
    private static function optionMap(array $options): array
    {
        $map = [];

        foreach ($options as $option) {
            $map[$option->name()] = $option;

            if ($option->shortAlias() !== null) {
                $map[$option->shortAlias()] = $option;
            }
        }

        return $map;
    }

    /**
     * @param list<string> $tokens
     *
     * @return array{array<string, ParsedOption>, list<string>}
     */
    private function parseGlobalOptions(array $tokens): array
    {
        $options = [];
        $index = 0;

        while ($index < count($tokens)) {
            $token = $tokens[$index];

            if ($token === '--') {
                return [$options, array_slice($tokens, $index + 1)];
            }

            if ($this->isCommandAlias($token) || ! str_starts_with($token, '-')) {
                return [$options, array_slice($tokens, $index)];
            }

            $this->parseOptionToken($tokens, $index, $this->globalOptions, $options);
            ++$index;
        }

        return [$options, []];
    }

    private function isCommandAlias(string $token): bool
    {
        return in_array($token, ['--help', '-h', '--version', '-V'], true);
    }

    /**
     * @param list<string> $tokens
     *
     * @return array{string, list<string>}
     */
    private function resolveCommand(array $tokens): array
    {
        $first = $tokens[0];
        $rest = array_slice($tokens, 1);

        return match ($first) {
            'help', '--help', '-h' => ['help', $rest],
            'version', '--version', '-V' => ['version', $rest],
            'init' => ['init', $rest],
            'validate' => ['validate', $rest],
            'run' => ['run', $rest],
            'add', 'list', 'view', 'runs' => throw new InvalidUsageException(sprintf('Unknown command "%s".', $first)),
            'skills' => $this->resolveSkillsCommand($rest),
            'tools' => $this->resolveToolsCommand($rest),
            'history' => $this->resolveHistoryCommand($rest),
            default => $this->resolveDirectToolCommand($tokens),
        };
    }

    /**
     * @param list<string> $tokens
     *
     * @return array{string, list<string>}
     */
    private function resolveDirectToolCommand(array $tokens): array
    {
        if (str_starts_with($tokens[0], '-')) {
            throw new InvalidUsageException(sprintf('Unknown option "%s".', $tokens[0]));
        }

        return ['run', $tokens];
    }

    /**
     * @param list<string> $tokens
     *
     * @return array{string, list<string>}
     */
    private function resolveSkillsCommand(array $tokens): array
    {
        $subcommand = $tokens[0] ?? '';
        $rest = array_slice($tokens, 1);

        return match ($subcommand) {
            'list', 'ls' => ['skills list', $rest],
            'add' => ['skills add', $rest],
            'find' => ['skills find', $rest],
            'init' => ['skills init', $rest],
            'remove', 'rm' => ['skills remove', $rest],
            'update' => ['skills update', $rest],
            default => throw new InvalidUsageException(sprintf('Unknown command "skills%s".', $subcommand === '' ? '' : ' ' . $subcommand)),
        };
    }

    /**
     * @param list<string> $tokens
     *
     * @return array{string, list<string>}
     */
    private function resolveToolsCommand(array $tokens): array
    {
        $subcommand = $tokens[0] ?? '';

        return match ($subcommand) {
            'list', 'ls' => ['tools list', array_slice($tokens, 1)],
            default => throw new InvalidUsageException(sprintf('Unknown command "tools%s".', $subcommand === '' ? '' : ' ' . $subcommand)),
        };
    }

    /**
     * @param list<string> $tokens
     *
     * @return array{string, list<string>}
     */
    private function resolveHistoryCommand(array $tokens): array
    {
        $subcommand = $tokens[0] ?? '';
        $rest = array_slice($tokens, 1);

        if ($this->isRunId($subcommand)) {
            return ['history view', $tokens];
        }

        return match ($subcommand) {
            'list', 'ls' => ['history list', $rest],
            'view' => ['history view', $rest],
            'clear' => ['history clear', $rest],
            'remove', 'rm' => ['history remove', $rest],
            default => throw new InvalidUsageException(sprintf('Unknown command "history%s".', $subcommand === '' ? '' : ' ' . $subcommand)),
        };
    }

    private function isRunId(string $token): bool
    {
        return preg_match('/^run_[a-f0-9]{32}$/', $token) === 1;
    }

    /**
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    private function stripEndOfOptionsMarker(array $tokens): array
    {
        $arguments = [];

        foreach ($tokens as $token) {
            if ($token === '--') {
                continue;
            }

            $arguments[] = $token;
        }

        return $arguments;
    }

    /**
     * @param list<string> $tokens
     *
     * @return array{array<string, ParsedOption>, list<string>}
     */
    private function parseCommandOptions(string $command, array $tokens): array
    {
        $optionSpecs = $this->commandOptions[$command] ?? [];
        $options = [];
        $arguments = [];
        $index = 0;

        while ($index < count($tokens)) {
            $token = $tokens[$index];

            if ($token === '--') {
                return [$options, [...$arguments, ...array_slice($tokens, $index + 1)]];
            }

            if (! str_starts_with($token, '-')) {
                $arguments[] = $token;
                ++$index;

                continue;
            }

            $this->parseOptionToken($tokens, $index, $optionSpecs, $options);
            ++$index;
        }

        return [$options, $arguments];
    }

    /**
     * @param list<string> $tokens
     * @param array<string, CliOption> $optionSpecs
     * @param array<string, ParsedOption> $options
     */
    private function parseOptionToken(array $tokens, int &$index, array $optionSpecs, array &$options): void
    {
        $token = $tokens[$index];
        [$optionKey, $displayName, $inlineValue] = $this->splitOptionToken($token);
        $option = $optionSpecs[$optionKey] ?? null;

        if (! $option instanceof CliOption) {
            throw new InvalidUsageException(sprintf('Unknown option "%s".', $displayName));
        }

        if ($option->type() === CliOptionType::Boolean) {
            if ($inlineValue !== null) {
                throw new InvalidUsageException(sprintf('Option "--%s" does not accept a value.', $option->name()));
            }

            $this->storeOptionValue($options, $option, true);

            return;
        }

        $value = $inlineValue;

        if ($value === null) {
            $nextToken = $tokens[$index + 1] ?? null;

            if ($nextToken === null || ! $this->isOptionValueToken($nextToken, $option)) {
                throw new InvalidUsageException(sprintf('Option "--%s" requires a value.', $option->name()));
            }

            $value = $nextToken;
            ++$index;
        }

        $this->storeOptionValue($options, $option, $this->coerceOptionValue($option, $value));
    }

    /**
     * @return array{string, string, string|null}
     */
    private function splitOptionToken(string $token): array
    {
        if (str_starts_with($token, '--')) {
            $option = substr($token, 2);

            if (str_contains($option, '=')) {
                $parts = explode('=', $option, 2);
                $name = $parts[0];
                $value = $parts[1];
            } else {
                $name = $option;
                $value = null;
            }

            return [$name, '--' . $name, $value];
        }

        $option = substr($token, 1);

        if (str_starts_with($option, 'd') && strlen($option) > 1) {
            return ['d', '-d', substr($option, 1)];
        }

        return [$option, '-' . $option, null];
    }

    private function isOptionValueToken(string $token, CliOption $option): bool
    {
        if ($option->type() === CliOptionType::Integer && preg_match('/^-?\d+$/', $token) === 1) {
            return true;
        }

        return ! str_starts_with($token, '-');
    }

    private function coerceOptionValue(CliOption $option, string $value): string|int
    {
        if ($option->type() !== CliOptionType::Integer) {
            return $value;
        }

        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw new InvalidUsageException(sprintf('Option "--%s" expects an integer.', $option->name()));
        }

        return (int) $value;
    }

    /**
     * @param array<string, ParsedOption> $options
     */
    private function storeOptionValue(array &$options, CliOption $option, bool|int|string $value): void
    {
        if (! $option->repeatable() && array_key_exists($option->name(), $options)) {
            throw new InvalidUsageException(sprintf('Option "--%s" cannot be repeated.', $option->name()));
        }

        if (! $option->repeatable()) {
            $options[$option->name()] = $value;

            return;
        }

        $currentValue = $options[$option->name()] ?? [];
        $options[$option->name()] = is_array($currentValue) ? [...$currentValue, $value] : [$currentValue, $value];
    }
}
