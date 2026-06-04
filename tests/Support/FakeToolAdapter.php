<?php

declare(strict_types=1);

namespace Tests\Support;

use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\ToolContext;

final readonly class FakeToolAdapter extends AbstractCliToolAdapter
{
    /**
     * @param non-empty-list<string> $binaryCandidates
     */
    public function __construct(
        private string $toolName = 'fake-tool',
        private array $binaryCandidates = [PHP_BINARY],
        private NormalizedResult $result = new NormalizedResult('fake-tool', \Sift\Core\RunStatus::Passed),
    ) {}

    protected function name(): string
    {
        return $this->toolName;
    }

    protected function description(): string
    {
        return 'Fake tool adapter.';
    }

    protected function binaryCandidates(): array
    {
        return $this->binaryCandidates;
    }

    protected function installHint(): string
    {
        return 'Install fake tool.';
    }

    protected function defaultContext(): string
    {
        return 'test';
    }

    public function prepare(LocatedTool $tool, ToolContext $context, \Sift\Config\ToolConfig $config): PreparedCommand
    {
        return $this->prepareBaseCommand($tool, $context, $config, ['--fake']);
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        return $this->result;
    }
}
