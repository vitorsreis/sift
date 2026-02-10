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

        foreach ($arguments as $index => $argument) {
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
            $toolArguments = array_slice($arguments, $index + 1);

            break;
        }

        return [
            'command' => $command ?? '--help',
            'pretty' => $pretty,
            'arguments' => $toolArguments,
        ];
    }
}
