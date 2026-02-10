<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Exceptions\UserFacingException;

final class OptionParser
{
    /**
     * @param  list<string>  $arguments
     * @return array{command: string, pretty: bool, arguments: list<string>}
     */
    public function parse(array $arguments): array
    {
        $pretty = false;
        $command = null;
        $toolArguments = [];

        foreach ($arguments as $argument) {
            if ($command === null) {
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
            'arguments' => $toolArguments,
        ];
    }
}
