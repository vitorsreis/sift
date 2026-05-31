<?php

declare(strict_types=1);

namespace Sift\Execution;

use Sift\Core\PreparedCommand;

final readonly class ProcessCommandBuilder
{
    public function __construct(
        private Platform $platform = new Platform(),
    ) {}

    /**
     * @return non-empty-list<string>
     */
    public function argv(PreparedCommand $command): array
    {
        if ($this->platform->isWindows() && $this->isBatchFile($command->binary())) {
            return [
                'cmd.exe',
                '/d',
                '/s',
                '/c',
                $this->windowsBatchCommandLine($command->argv()),
            ];
        }

        return $command->argv();
    }

    private function isBatchFile(string $binary): bool
    {
        $extension = strtolower(pathinfo(str_replace('\\', '/', $binary), PATHINFO_EXTENSION));

        return $extension === 'bat' || $extension === 'cmd';
    }

    /**
     * @param non-empty-list<string> $argv
     */
    private function windowsBatchCommandLine(array $argv): string
    {
        return implode(' ', array_map($this->quoteWindowsArgument(...), $argv));
    }

    private function quoteWindowsArgument(string $argument): string
    {
        return '"' . str_replace('"', '\\"', $argument) . '"';
    }
}
