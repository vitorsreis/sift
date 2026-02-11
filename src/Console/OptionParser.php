<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Exceptions\UserFacingException;

final class OptionParser
{
    /**
     * @param  list<string>  $arguments
     * @return array{command: string, pretty: ?bool, format: ?string, size: ?string, history: ?bool, config: ?string, arguments: list<string>}
     */
    public function parse(array $arguments): array
    {
        $pretty = null;
        $format = null;
        $size = null;
        $history = null;
        $config = null;
        $command = null;
        $toolArguments = [];

        foreach ($arguments as $argument) {
            if ($command === null) {
                if ($this->parseRuntimeOption($argument, $format, $size, $pretty, $history, $config)) {
                    continue;
                }

                if (str_starts_with($argument, '--') && ! in_array($argument, ['--help', '--version'], true)) {
                    throw UserFacingException::invalidUsage(sprintf('Unknown option: %s', $argument));
                }

                $command = $argument;

                continue;
            }

            if (in_array($command, ['help', 'version', 'list', '--help', '--version', '-h', '-V'], true)) {
                if ($this->parseRuntimeOption($argument, $format, $size, $pretty, $history, $config)) {
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
            'history' => $history,
            'config' => $config,
            'arguments' => $toolArguments,
        ];
    }

    private function parseRuntimeOption(
        string $argument,
        ?string &$format,
        ?string &$size,
        ?bool &$pretty,
        ?bool &$history,
        ?string &$config,
    ): bool {
        if ($this->parseOutputOption($argument, $format, $size, $pretty)) {
            return true;
        }

        if ($argument === '--no-history') {
            $history = false;

            return true;
        }

        if (str_starts_with($argument, '--config=')) {
            $config = $this->parseConfigPath(substr($argument, 9));

            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $arguments
     * @return array{run_id: ?string, scope: string, limit: int, offset: int, list: bool, clear: bool, format: ?string, size: ?string, pretty: ?bool}
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

        foreach ($arguments as $argument) {
            if ($this->parseOutputOption($argument, $format, $size, $pretty)) {
                continue;
            }

            if ($argument === '--clear') {
                $clear = true;

                continue;
            }

            if (str_starts_with($argument, '--limit=')) {
                $limit = max(1, (int) substr($argument, 8));

                continue;
            }

            if (str_starts_with($argument, '--offset=')) {
                $offset = max(0, (int) substr($argument, 9));

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
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array{force: bool, format: ?string, size: ?string, pretty: ?bool}
     */
    public function parseInit(array $arguments): array
    {
        $force = false;
        $format = null;
        $size = null;
        $pretty = null;

        foreach ($arguments as $argument) {
            if ($this->parseOutputOption($argument, $format, $size, $pretty)) {
                continue;
            }

            if ($argument === '--force') {
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
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array{format: ?string, size: ?string, pretty: ?bool}
     */
    public function parseValidate(array $arguments): array
    {
        $format = null;
        $size = null;
        $pretty = null;

        foreach ($arguments as $argument) {
            if ($this->parseOutputOption($argument, $format, $size, $pretty)) {
                continue;
            }

            throw UserFacingException::invalidUsage(sprintf('Unknown validate option: %s', $argument));
        }

        return [
            'format' => $format,
            'size' => $size,
            'pretty' => $pretty,
        ];
    }

    private function parseOutputOption(string $argument, ?string &$format, ?string &$size, ?bool &$pretty): bool
    {
        if (str_starts_with($argument, '--format=')) {
            $format = $this->parseFormat(substr($argument, 9));

            return true;
        }

        if (str_starts_with($argument, '--size=')) {
            $size = $this->parseSize(substr($argument, 7));

            return true;
        }

        if ($argument === '--pretty') {
            $pretty = true;

            return true;
        }

        if ($argument === '--no-pretty') {
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

    private function parseConfigPath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw UserFacingException::invalidUsage('The config path must not be empty.');
        }

        return $path;
    }
}
