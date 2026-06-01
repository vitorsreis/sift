<?php

declare(strict_types=1);

namespace Sift\Execution;

use Sift\Core\PreparedCommand;

final readonly class PhpCommandFactory
{
    public function __construct(
        private string $phpBinary = PHP_BINARY,
        private PhpRuntimeArguments $runtimeArguments = new PhpRuntimeArguments(),
    ) {}

    /**
     * @param list<string> $phpArguments
     */
    public function apply(PreparedCommand $command, array $phpArguments): PreparedCommand
    {
        $script = $this->phpScript($command->binary());

        if ($script === null) {
            return $command;
        }

        $phpArguments = [...$this->runtimeArguments->arguments(), ...$phpArguments];

        if ($phpArguments === []) {
            return $command;
        }

        return new PreparedCommand(
            tool: $command->tool(),
            binary: $this->phpBinary,
            arguments: [...$phpArguments, $script, ...$command->arguments()],
            cwd: $command->cwd(),
            environment: $command->environment(),
            timeout: $command->timeout(),
            temporaryFiles: $command->temporaryFiles(),
            artifacts: $command->artifacts(),
            displayCommand: [$this->phpBinary, ...$phpArguments, $script, ...$command->arguments()],
            nativeOutputMode: $command->nativeOutputMode(),
        );
    }

    private function phpScript(string $binary): ?string
    {
        if ($this->isPhpScript($binary)) {
            return $binary;
        }

        if (! $this->isWindowsBatch($binary)) {
            return null;
        }

        $sibling = preg_replace('/\.(?:bat|cmd)$/i', '', $binary);

        return is_string($sibling) && $this->isPhpScript($sibling) ? $sibling : null;
    }

    private function isWindowsBatch(string $path): bool
    {
        $extension = strtolower(pathinfo(str_replace('\\', '/', $path), PATHINFO_EXTENSION));

        return $extension === 'bat' || $extension === 'cmd';
    }

    private function isPhpScript(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $contents = file_get_contents($path, false, null, 0, 512);

        if (! is_string($contents)) {
            return false;
        }

        return str_starts_with($contents, "#!/usr/bin/env php")
            || str_starts_with($contents, "#!php")
            || str_contains($contents, '<?php');
    }
}
