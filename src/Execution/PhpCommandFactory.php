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

        $phpArguments = $this->mergePhpArguments($this->runtimeArguments->arguments(), $phpArguments);

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

    /**
     * @param list<string> $runtimeArguments
     * @param list<string> $toolArguments
     *
     * @return list<string>
     */
    private function mergePhpArguments(array $runtimeArguments, array $toolArguments): array
    {
        $toolArgumentKeys = array_flip(array_map($this->phpArgumentKey(...), $toolArguments));
        $arguments = [];

        foreach ($runtimeArguments as $argument) {
            if (array_key_exists($this->phpArgumentKey($argument), $toolArgumentKeys)) {
                continue;
            }

            $arguments[] = $argument;
        }

        return [...$arguments, ...$toolArguments];
    }

    private function phpArgumentKey(string $argument): string
    {
        if (! str_starts_with($argument, '-d') || strlen($argument) <= 2) {
            return $argument;
        }

        $definition = substr($argument, 2);
        $separator = strpos($definition, '=');
        $name = $separator === false ? $definition : substr($definition, 0, $separator);

        return '-d' . strtolower($name);
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
