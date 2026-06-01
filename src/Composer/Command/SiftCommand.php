<?php

declare(strict_types=1);

namespace Sift\Composer\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SiftCommand extends ApplicationCommand
{
    protected function configure(): void
    {
        $this->configureBridge('sift', 'Run Sift commands from Composer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runSiftApplication($this->siftArguments($input), $output);
    }
}
