<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Contracts\PolicyInterface;
use Sift\Contracts\ToolAdapterInterface;
use Sift\Exceptions\UserFacingException;

final class RectorCommandPolicy implements PolicyInterface
{
    public function apply(string $cwd, ToolAdapterInterface $tool, array $arguments, array $toolConfig): void
    {
        unset($cwd, $toolConfig);

        if ($tool->name() !== 'rector') {
            return;
        }

        if ($this->command($arguments) !== 'process') {
            throw UserFacingException::invalidUsage('Sift currently supports only `rector process`.');
        }

        if (! $this->hasOption($arguments, '--dry-run')) {
            throw UserFacingException::invalidUsage('Rector write mode is blocked by Sift. Run `rector process --dry-run ...` instead.');
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function command(array $arguments): string
    {
        $command = $arguments[0] ?? 'process';

        if ($command === '' || str_starts_with($command, '-')) {
            return 'process';
        }

        return $command;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function hasOption(array $arguments, string $option): bool
    {
        foreach ($arguments as $argument) {
            if ($argument === $option || str_starts_with($argument, $option.'=')) {
                return true;
            }
        }

        return false;
    }
}
