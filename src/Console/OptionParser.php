<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Exceptions\UserFacingException;

final class OptionParser
{
    /**
     * @param  list<string>  $arguments
     * @return array{command: string, pretty: bool, format: string, size: string, arguments: list<string>}
     */
    public function parse(array $arguments): array
    {
        $pretty = false;
        $format = 'json';
        $size = 'normal';
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
