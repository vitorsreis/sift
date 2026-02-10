<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Exceptions\UserFacingException;

final class OptionParser
{
    /**
     * @param  list<string>  $arguments
     * @return array{command: string, pretty: ?bool, format: ?string, size: ?string, arguments: list<string>}
     */
    public function parse(array $arguments): array
    {
        $pretty = null;
        $format = null;
        $size = null;
        $command = null;
        $toolArguments = [];

        foreach ($arguments as $argument) {
            if ($command === null) {
                if (str_starts_with($argument, '--format=')) {
                    $format = $this->parseFormat(substr($argument, 9));

                    continue;
                }

                if (str_starts_with($argument, '--size=')) {
                    $size = $this->parseSize(substr($argument, 7));

                    continue;
                }

                if ($argument === '--pretty') {
                    $pretty = true;

                    continue;
                }

                if ($argument === '--no-pretty') {
                    $pretty = false;

                    continue;
                }

                if (str_starts_with($argument, '--') && ! in_array($argument, ['--help', '--version'], true)) {
                    throw UserFacingException::invalidUsage(sprintf('Unknown option: %s', $argument));
                }

                $command = $argument;

                continue;
            }

            if (in_array($command, ['help', 'version', 'list', '--help', '--version', '-h', '-V'], true)) {
                if (str_starts_with($argument, '--format=')) {
                    $format = $this->parseFormat(substr($argument, 9));

                    continue;
                }

                if (str_starts_with($argument, '--size=')) {
                    $size = $this->parseSize(substr($argument, 7));

                    continue;
                }

                if ($argument === '--pretty') {
                    $pretty = true;

                    continue;
                }

                if ($argument === '--no-pretty') {
                    $pretty = false;

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
            'arguments' => $toolArguments,
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array{run_id: ?string, scope: string, limit: int, offset: int, list: bool, format: ?string, size: ?string, pretty: ?bool}
     */
    public function parseView(array $arguments): array
    {
        $positionals = [];
        $limit = 10;
        $offset = 0;
        $format = null;
        $size = null;
        $pretty = null;

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--format=')) {
                $format = $this->parseFormat(substr($argument, 9));

                continue;
            }

            if (str_starts_with($argument, '--size=')) {
                $size = $this->parseSize(substr($argument, 7));

                continue;
            }

            if ($argument === '--pretty') {
                $pretty = true;

                continue;
            }

            if ($argument === '--no-pretty') {
                $pretty = false;

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

        if ($positionals === [] || in_array($positionals[0], ['list', 'runs'], true)) {
            return [
                'run_id' => null,
                'scope' => 'runs',
                'limit' => $limit,
                'offset' => $offset,
                'list' => true,
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
            if (str_starts_with($argument, '--format=')) {
                $format = $this->parseFormat(substr($argument, 9));

                continue;
            }

            if (str_starts_with($argument, '--size=')) {
                $size = $this->parseSize(substr($argument, 7));

                continue;
            }

            if ($argument === '--pretty') {
                $pretty = true;

                continue;
            }

            if ($argument === '--no-pretty') {
                $pretty = false;

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
            if (str_starts_with($argument, '--format=')) {
                $format = $this->parseFormat(substr($argument, 9));

                continue;
            }

            if (str_starts_with($argument, '--size=')) {
                $size = $this->parseSize(substr($argument, 7));

                continue;
            }

            if ($argument === '--pretty') {
                $pretty = true;

                continue;
            }

            if ($argument === '--no-pretty') {
                $pretty = false;

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
}
