<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Exceptions\UserFacingException;

final class OptionParser
{
    /**
     * @param  list<string>  $arguments
     * @return array{command: string, pretty: ?bool, format: ?string, size: ?string, raw: ?bool, show_process: ?bool, history: ?bool, config: ?string, arguments: list<string>}
     */
    public function parse(array $arguments): array
    {
        $pretty = null;
        $format = null;
        $size = null;
        $raw = null;
        $showProcess = null;
        $history = null;
        $config = null;
        $command = null;
        $toolArguments = [];
        $count = count($arguments);

        for ($index = 0; $index < $count; $index++) {
            $argument = $arguments[$index];

            if ($command === null) {
                if ($this->parseRuntimeOption($arguments, $index, $format, $size, $pretty, $raw, $showProcess, $history, $config)) {
                    continue;
                }

                if ($this->looksLikeUnknownOption($argument, ['--help', '--version', '-h', '-V'])) {
                    throw UserFacingException::invalidUsage(sprintf('Unknown option: %s', $argument));
                }

                $command = $argument;

                continue;
            }

            if (in_array($command, ['help', 'version', 'list', '--help', '--version', '-h', '-V'], true)) {
                if ($this->parseRuntimeOption($arguments, $index, $format, $size, $pretty, $raw, $showProcess, $history, $config)) {
                    continue;
                }

                throw UserFacingException::invalidUsage(sprintf('Unknown option: %s', $argument));
            }

            $toolArguments[] = $argument;
        }

        return [
            'command' => $command ?? '--help',
            'pretty' => $pretty,
            'format' => $format,
            'size' => $size,
            'raw' => $raw,
            'show_process' => $showProcess,
            'history' => $history,
            'config' => $config,
            'arguments' => $toolArguments,
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array{run_id: ?string, scope: string, limit: int, offset: int, list: bool, clear: bool, format: ?string, size: ?string, pretty: ?bool, config: ?string}
     */
    public function parseView(array $arguments): array
    {
        $positionals = [];
        $limit = 10;
        $offset = 0;
        $format = null;
        $size = null;
        $pretty = null;
        $clear = false;
        $config = null;
        $count = count($arguments);

        for ($index = 0; $index < $count; $index++) {
            $argument = $arguments[$index];

            if ($this->parseOutputOption($arguments, $index, $format, $size, $pretty)) {
                continue;
            }

            if ($this->parseConfigOption($arguments, $index, $config)) {
                continue;
            }

            if ($argument === '--clear') {
                $clear = true;

                continue;
            }

            if (($value = $this->parseIntegerOption($arguments, $index, '--limit', '-l')) !== null) {
                $limit = max(1, $value);

                continue;
            }

            if (($value = $this->parseIntegerOption($arguments, $index, '--offset', '-o')) !== null) {
                $offset = max(0, $value);

                continue;
            }

            $positionals[] = $argument;
        }

        if ($clear) {
            if ($positionals !== []) {
                throw UserFacingException::invalidUsage('The `view --clear` command does not accept a run id or scope.');
            }

            return [
                'run_id' => null,
                'scope' => 'clear',
                'limit' => $limit,
                'offset' => $offset,
                'list' => false,
                'clear' => true,
                'format' => $format,
                'size' => $size,
                'pretty' => $pretty,
                'config' => $config,
            ];
        }

        if ($positionals === [] || in_array($positionals[0], ['list', 'runs'], true)) {
            return [
                'run_id' => null,
                'scope' => 'runs',
                'limit' => $limit,
                'offset' => $offset,
                'list' => true,
                'clear' => false,
                'format' => $format,
                'size' => $size,
                'pretty' => $pretty,
                'config' => $config,
            ];
        }

        return [
            'run_id' => $positionals[0],
            'scope' => $positionals[1] ?? 'fuller',
            'limit' => $limit,
            'offset' => $offset,
            'list' => false,
            'clear' => false,
            'format' => $format,
            'size' => $size,
            'pretty' => $pretty,
            'config' => $config,
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array{force: bool, format: ?string, size: ?string, pretty: ?bool, config: ?string}
     */
    public function parseInit(array $arguments): array
    {
        $force = false;
        $format = null;
        $size = null;
        $pretty = null;
        $config = null;
        $count = count($arguments);

        for ($index = 0; $index < $count; $index++) {
            $argument = $arguments[$index];

            if ($this->parseOutputOption($arguments, $index, $format, $size, $pretty)) {
                continue;
            }

            if ($this->parseConfigOption($arguments, $index, $config)) {
                continue;
            }

            if ($argument === '--force' || $argument === '-F') {
                $force = true;

                continue;
            }

            throw UserFacingException::invalidUsage(sprintf('Unknown init option: %s', $argument));
        }

        return [
            'force' => $force,
            'format' => $format,
            'size' => $size,
            'pretty' => $pretty,
            'config' => $config,
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array{format: ?string, size: ?string, pretty: ?bool, config: ?string}
     */
    public function parseValidate(array $arguments): array
    {
        $format = null;
        $size = null;
        $pretty = null;
        $config = null;
        $count = count($arguments);

        for ($index = 0; $index < $count; $index++) {
            $argument = $arguments[$index];

            if ($this->parseOutputOption($arguments, $index, $format, $size, $pretty)) {
                continue;
            }

            if ($this->parseConfigOption($arguments, $index, $config)) {
                continue;
            }

            throw UserFacingException::invalidUsage(sprintf('Unknown validate option: %s', $argument));
        }

        return [
            'format' => $format,
            'size' => $size,
            'pretty' => $pretty,
            'config' => $config,
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array{tool: ?string, interactive: bool, format: ?string, size: ?string, pretty: ?bool, config: ?string}
     */
    public function parseAdd(array $arguments): array
    {
        $positionals = [];
        $format = null;
        $size = null;
        $pretty = null;
        $config = null;
        $count = count($arguments);

        for ($index = 0; $index < $count; $index++) {
            $argument = $arguments[$index];

            if ($this->parseOutputOption($arguments, $index, $format, $size, $pretty)) {
                continue;
            }

            if ($this->parseConfigOption($arguments, $index, $config)) {
                continue;
            }

            if (str_starts_with($argument, '-')) {
                throw UserFacingException::invalidUsage(sprintf('Unknown add option: %s', $argument));
            }

            $positionals[] = $argument;
        }

        if (count($positionals) > 1) {
            throw UserFacingException::invalidUsage('The `add` command accepts at most one supported tool name.');
        }

        return [
            'tool' => $positionals[0] ?? null,
            'interactive' => $positionals === [],
            'format' => $format,
            'size' => $size,
            'pretty' => $pretty,
            'config' => $config,
        ];
    }

    /**
     * @param  list<string>  $arguments
     */
    private function parseRuntimeOption(
        array $arguments,
        int &$index,
        ?string &$format,
        ?string &$size,
        ?bool &$pretty,
        ?bool &$raw,
        ?bool &$showProcess,
        ?bool &$history,
        ?string &$config,
    ): bool {
        if ($this->parseOutputOption($arguments, $index, $format, $size, $pretty)) {
            return true;
        }

        $argument = $arguments[$index];

        if ($argument === '--raw' || $argument === '-r') {
            $raw = true;

            return true;
        }

        if ($argument === '--show-process') {
            $showProcess = true;

            return true;
        }

        if ($argument === '--no-show-process') {
            $showProcess = false;

            return true;
        }

        if ($argument === '--no-history') {
            $history = false;

            return true;
        }

        return $this->parseConfigOption($arguments, $index, $config);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function parseOutputOption(array $arguments, int &$index, ?string &$format, ?string &$size, ?bool &$pretty): bool
    {
        if (($value = $this->parseStringOption($arguments, $index, '--format', '-f')) !== null) {
            $format = $this->parseFormat($value);

            return true;
        }

        if (($value = $this->parseStringOption($arguments, $index, '--size', '-s')) !== null) {
            $size = $this->parseSize($value);

            return true;
        }

        $argument = $arguments[$index];

        if ($argument === '--pretty' || $argument === '-p') {
            $pretty = true;

            return true;
        }

        if ($argument === '--no-pretty' || $argument === '-P') {
            $pretty = false;

            return true;
        }

        return false;
    }

    private function parseFormat(string $format): string
    {
        return match ($format) {
            'json', 'markdown' => $format,
            default => throw UserFacingException::invalidUsage(sprintf('Unknown format: %s', $format)),
        };
    }

    private function parseSize(string $size): string
    {
        return match ($size) {
            'compact', 'normal', 'fuller' => $size,
            default => throw UserFacingException::invalidUsage(sprintf('Unknown size: %s', $size)),
        };
    }

    /**
     * @param  list<string>  $arguments
     */
    private function parseConfigOption(array $arguments, int &$index, ?string &$config): bool
    {
        $value = $this->parseStringOption($arguments, $index, '--config', '-c');

        if ($value === null) {
            return false;
        }

        $config = $this->parseConfigPath($value);

        return true;
    }

    private function parseConfigPath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw UserFacingException::invalidUsage('The config path must not be empty.');
        }

        return $path;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function parseStringOption(array $arguments, int &$index, string $longOption, ?string $shortOption = null): ?string
    {
        $argument = $arguments[$index];

        foreach (array_filter([$longOption, $shortOption]) as $option) {
            if ($argument === $option) {
                $value = $arguments[$index + 1] ?? null;

                if (! is_string($value) || $value === '' || str_starts_with($value, '-')) {
                    throw UserFacingException::invalidUsage(sprintf('The option `%s` requires a value.', $option));
                }

                $index++;

                return $value;
            }

            if (str_starts_with($argument, $option.'=')) {
                return substr($argument, strlen($option) + 1);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function parseIntegerOption(array $arguments, int &$index, string $longOption, ?string $shortOption = null): ?int
    {
        $value = $this->parseStringOption($arguments, $index, $longOption, $shortOption);

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function looksLikeUnknownOption(string $argument, array $allowed): bool
    {
        if (! str_starts_with($argument, '-')) {
            return false;
        }

        return ! in_array($argument, $allowed, true);
    }
}
