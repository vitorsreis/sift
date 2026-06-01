<?php

declare(strict_types=1);

namespace Sift\Composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Sift\Composer\Command\ComposerSkillsCommand;
use Sift\Composer\Command\SiftCommand;
use Symfony\Component\Console\Command\Command;

final class SiftCommandProvider implements CommandProviderCapability
{
    /**
     * @return list<Command>
     */
    public function getCommands(): array
    {
        return [
            new SiftCommand(),
            new ComposerSkillsCommand(),
        ];
    }
}
