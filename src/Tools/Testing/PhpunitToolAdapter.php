<?php

declare(strict_types=1);

namespace Sift\Tools\Testing;

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

    #[\Override]
    protected function versionCommand(): array
    {
        return ['--version'];
    }
}
