<?php

declare(strict_types=1);

namespace Sift\Contracts;

interface PolicyInterface
{
    /**
     * @param  list<string>  $arguments
     * @param  array{enabled: bool, toolBinary: ?string, defaultArgs: list<string>, blockedArgs: list<string>}  $toolConfig
     */
    public function apply(string $cwd, ToolAdapterInterface $tool, array $arguments, array $toolConfig): void;
}
