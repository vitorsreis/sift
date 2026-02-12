<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Contracts\PolicyInterface;
use Sift\Contracts\ToolAdapterInterface;
use Sift\Exceptions\UserFacingException;

final readonly class ToolInstalledPolicy implements PolicyInterface
{
    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function apply(string $cwd, ToolAdapterInterface $tool, array $arguments, array $toolConfig): void
    {
        $configuredBinary = $toolConfig['toolBinary'];
        $candidates = $configuredBinary !== null
            ? [$configuredBinary]
            : $tool->discoveryCandidates();

        if ($this->toolLocator->locate($cwd, $candidates) !== null) {
            return;
        }

        throw UserFacingException::toolNotInstalled($tool->name(), $tool->installHint());
    }
}
