<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Contracts\PolicyInterface;
use Sift\Contracts\ToolAdapterInterface;
use Sift\Exceptions\UserFacingException;

final class ComposerCommandPolicy implements PolicyInterface
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_SUBCOMMANDS = ['audit', 'licenses', 'outdated', 'show'];

    public function apply(string $cwd, ToolAdapterInterface $tool, array $arguments, array $toolConfig): void
    {
        unset($cwd, $toolConfig);

        if ($tool->name() !== 'composer') {
            return;
        }

        $subcommand = $this->subcommand($arguments);
        $supported = implode(', ', self::SUPPORTED_SUBCOMMANDS);

        if ($subcommand === '') {
            throw UserFacingException::invalidUsage(
                sprintf('Sift supports only read-only Composer subcommands: %s.', $supported),
            );
        }

        if (! in_array($subcommand, self::SUPPORTED_SUBCOMMANDS, true)) {
            throw UserFacingException::invalidUsage(
                sprintf(
                    'The Composer subcommand `%s` is not supported by Sift. Sift supports only read-only Composer subcommands: %s.',
                    $subcommand,
                    $supported,
                ),
            );
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function subcommand(array $arguments): string
    {
        foreach ($arguments as $argument) {
            if ($argument !== '' && ! str_starts_with($argument, '-')) {
                return $argument;
            }
        }

        return '';
    }
}
