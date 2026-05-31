<?php

declare(strict_types=1);

namespace Sift\Tools\PhpCs;

use Sift\Config\ToolConfig;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class PhpcsToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private PhpcsParser $parser = new PhpcsParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'phpcs';
    }

    protected function description(): string
    {
        return 'PHP_CodeSniffer linter.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/phpcs.bat', 'vendor/bin/phpcs', 'phpcs'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev squizlabs/php_codesniffer';
    }

    protected function defaultContext(): string
    {
        return 'style';
    }

    #[\Override]
    protected function versionCommand(): array
    {
        return ['--version'];
    }

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        return $this->prepareBaseCommand(
            tool: $tool,
            context: $context,
            config: $config,
            arguments: $this->arguments($context->userArgs()),
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        $report = $this->parser->parse($execution->stdout(), $execution->stderr(), $context->cwd());

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->statusDecider->decide($execution, findings: $report->findings()),
            summary: $report->summary(),
            items: $report->items(),
        );
    }

    /**
     * @param list<string> $userArguments
     * @return list<string>
     */
    private function arguments(array $userArguments): array
    {
        $arguments = [];

        if (! $this->hasOption($userArguments, '--report')) {
            $arguments[] = '--report=json';
        }

        if (! in_array('-q', $userArguments, true) && ! in_array('--quiet', $userArguments, true)) {
            $arguments[] = '-q';
        }

        if (! in_array('--no-colors', $userArguments, true) && ! in_array('--no-colours', $userArguments, true)) {
            $arguments[] = '--no-colors';
        }

        return [...$arguments, ...$userArguments];
    }

    /**
     * @param list<string> $arguments
     */
    private function hasOption(array $arguments, string $option): bool
    {
        foreach ($arguments as $argument) {
            if ($argument === $option || str_starts_with($argument, $option . '=')) {
                return true;
            }
        }

        return false;
    }
}
