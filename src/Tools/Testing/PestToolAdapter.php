<?php

declare(strict_types=1);

namespace Sift\Tools\Testing;

final readonly class PestToolAdapter extends AbstractTestRunnerToolAdapter
{
    protected function name(): string
    {
        return 'pest';
    }

    #[\Override]
    protected function aliases(): array
    {
        return ['test', 'tests'];
    }

    protected function description(): string
    {
        return 'Pest test runner.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/pest.bat', 'vendor/bin/pest', 'pest'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev pestphp/pest';
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
