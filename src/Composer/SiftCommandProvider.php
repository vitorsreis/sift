<?php

declare(strict_types=1);

namespace Sift\Composer;

use Composer\Command\BaseCommand;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Sift\Composer\Command\ComposerSkillsCommand;
use Sift\Composer\Command\SiftCommand;

final class SiftCommandProvider implements CommandProviderCapability
{
    /**
     * @return list<BaseCommand>
     */
    public function getCommands(): array
    {
        return [
            new SiftCommand(),
            new ComposerSkillsCommand(),
        ];
    }
}
