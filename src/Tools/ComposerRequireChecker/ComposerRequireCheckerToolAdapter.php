<?php

declare(strict_types=1);

namespace Sift\Tools\ComposerRequireChecker;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class ComposerRequireCheckerToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private ComposerRequireCheckerParser $parser = new ComposerRequireCheckerParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'composer-require-checker';
    }

    protected function description(): string
    {
        return 'Composer dependency requirement checker.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/composer-require-checker.bat', 'vendor/bin/composer-require-checker', 'composer-require-checker'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev maglnet/composer-require-checker';
    }

    protected function defaultContext(): string
    {
        return 'dependency';
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
            arguments: $context->raw() ? $context->userArgs() : $this->jsonArguments($context->userArgs()),
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        $report = $this->parser->parse($execution->stdout(), $execution->stderr());

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->statusDecider->decide($execution, findings: $report->unknownSymbols()),
            summary: $report->summary(),
            items: $report->items(),
        );
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function jsonArguments(array $arguments): array
    {
        $arguments = $this->withoutCheckCommand($arguments);
        $output = $this->optionValue($arguments, '--output');

        if ($output !== null && strtolower($output) !== 'json') {
            throw new InvalidUsageException('Composer Require Checker adapter requires JSON output outside raw mode.');
        }

        $defaults = ['check'];

        if ($output === null) {
            $defaults[] = '--output=json';
        }

        return [...$defaults, ...$arguments];
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function withoutCheckCommand(array $arguments): array
    {
        $first = $arguments[0] ?? null;

        if ($first === null || str_starts_with($first, '-')) {
            return $arguments;
        }

        if ($first === 'check') {
            return array_slice($arguments, 1);
        }

        if (in_array($first, ['completion', 'help', 'list'], true)) {
            throw new InvalidUsageException('Composer Require Checker adapter supports only the "check" command.');
        }

        return $arguments;
    }

    /**
     * @param list<string> $arguments
     */
    private function optionValue(array $arguments, string $option): ?string
    {
        foreach ($arguments as $index => $argument) {
            if (str_starts_with($argument, $option . '=')) {
                return substr($argument, strlen($option) + 1);
            }

            if ($argument === $option) {
                $value = $arguments[$index + 1] ?? null;

                return is_string($value) ? $value : null;
            }
        }

        return null;
    }
}
