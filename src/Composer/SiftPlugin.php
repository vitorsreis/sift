<?php

declare(strict_types=1);

namespace Sift\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

final class SiftPlugin implements Capable, PluginInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
        unset($composer, $io);
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        unset($composer, $io);
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        unset($composer, $io);
    }

    /**
     * @return array<class-string, class-string>
     */
    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => SiftCommandProvider::class,
        ];
    }
}
