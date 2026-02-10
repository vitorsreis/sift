<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Sift;

final class Application
{
    /**
     * @param  list<string>  $argv
     */
    public static function run(array $argv): int
    {
        $command = $argv[1] ?? '--help';

        if (in_array($command, ['--version', '-V', 'version'], true)) {
            fwrite(STDOUT, Sift::VERSION.PHP_EOL);

            return 0;
        }

        if (in_array($command, ['--help', '-h', 'help'], true)) {
            fwrite(STDOUT, self::helpText());

            return 0;
        }

        fwrite(STDERR, sprintf(
            "Command `%s` is not implemented yet.\n\n%s",
            $command,
            self::helpText(),
        ));

        return 1;
    }

    private static function helpText(): string
    {
        return <<<'TEXT'
Sift 0.1.0-dev

Usage:
  sift <command>

Commands:
  help       Show help
  version    Show version

TEXT;
    }
}
