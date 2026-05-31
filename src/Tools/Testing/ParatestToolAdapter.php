<?php

declare(strict_types=1);

namespace Sift\Tools\Testing;

final readonly class ParatestToolAdapter extends AbstractTestRunnerToolAdapter
{
    protected function name(): string
    {
        return 'paratest';
    }

    protected function description(): string
    {
        return 'ParaTest parallel test runner.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/paratest.bat', 'vendor/bin/paratest', 'paratest'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev brianium/paratest';
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
