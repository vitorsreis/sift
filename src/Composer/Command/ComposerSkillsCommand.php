<?php

declare(strict_types=1);

namespace Sift\Composer\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ComposerSkillsCommand extends ApplicationCommand
{
    protected function configure(): void
    {
        $this->configureBridge('skills', 'Install, list, update, or remove Sift agent skills.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runSiftApplication($this->namespacedArguments('skills', $this->siftArguments($input)), $output);
    }
}
