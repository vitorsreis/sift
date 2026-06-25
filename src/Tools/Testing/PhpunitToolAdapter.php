<?php

declare(strict_types=1);

namespace Sift\Tools\Testing;

use Sift\Tools\ToolContext;

final readonly class PhpunitToolAdapter extends AbstractTestRunnerToolAdapter
{
    protected function name(): string
    {
        return 'phpunit';
    }

    protected function description(): string
    {
        return 'PHPUnit test runner.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/phpunit.bat', 'vendor/bin/phpunit', 'phpunit'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev phpunit/phpunit';
    }

    protected function defaultContext(): string
    {
        return 'test';
    }

    /**
     * @return list<string>
     */
    #[\Override]
    protected function userArguments(ToolContext $context): array
    {
        $arguments = [];
        $skipNext = false;
        $userArgs = $context->userArgs();

        foreach ($userArgs as $index => $argument) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if (str_starts_with($argument, '--min=')) {
                continue;
            }

            if ($argument === '--min') {
                $next = $userArgs[$index + 1] ?? null;
                $skipNext = is_string($next) && $this->isValueToken($next);

                continue;
            }

            $arguments[] = $argument;
        }

        return $arguments;
    }

    private function isValueToken(string $token): bool
    {
        return ! str_starts_with($token, '-') || preg_match('/^-?(?:\d+(?:\.\d+)?|\.\d+)$/', $token) === 1;
    }

    #[\Override]
    protected function versionCommand(): array
    {
        return ['--version'];
    }
}
