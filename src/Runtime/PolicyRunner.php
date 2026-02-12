<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Contracts\PolicyInterface;
use Sift\Contracts\ToolAdapterInterface;

final readonly class PolicyRunner
{
    /**
     * @param  list<PolicyInterface>  $policies
     */
    public function __construct(
        private array $policies,
    ) {}

    /**
     * @param  list<string>  $arguments
     * @param  array{enabled: bool, toolBinary: ?string, defaultArgs: list<string>, blockedArgs: list<string>}  $toolConfig
     */
    public function enforce(string $cwd, ToolAdapterInterface $tool, array $arguments, array $toolConfig): void
    {
        foreach ($this->policies as $policy) {
            $policy->apply($cwd, $tool, $arguments, $toolConfig);
        }
    }
}
