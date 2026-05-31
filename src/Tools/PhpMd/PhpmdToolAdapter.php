<?php

declare(strict_types=1);

namespace Sift\Tools\PhpMd;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class PhpmdToolAdapter extends AbstractCliToolAdapter
{
    private const string DEFAULT_RULESETS = 'cleancode,codesize,controversial,design,naming,unusedcode';

    /**
     * @var list<string>
     */
    private const array NON_JSON_FORMATS = ['ansi', 'html', 'text', 'xml'];

    public function __construct(
        private PhpmdParser $parser = new PhpmdParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'phpmd';
    }

    protected function description(): string
    {
        return 'PHPMD static code analyzer.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/phpmd.bat', 'vendor/bin/phpmd', 'phpmd'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev phpmd/phpmd';
    }

    protected function defaultContext(): string
    {
        return 'quality';
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
        $report = $this->parser->parse($execution->stdout(), $execution->stderr(), $context->cwd());

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->statusDecider->decide($execution, findings: $report->violations()),
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
        $parts = $this->positionalPrefix($arguments);
        $positionals = $parts['positionals'];
        $trailing = $parts['trailing'];

        if ($positionals === []) {
            return ['.', 'json', self::DEFAULT_RULESETS, ...$arguments];
        }

        if (count($positionals) === 1) {
            return [$positionals[0], 'json', self::DEFAULT_RULESETS, ...$trailing];
        }

        $formatOrRuleset = $positionals[1];

        if (strtolower($formatOrRuleset) === 'json') {
            $ruleset = $positionals[2] ?? self::DEFAULT_RULESETS;

            return [$positionals[0], 'json', $ruleset, ...array_slice($positionals, 3), ...$trailing];
        }

        if (in_array(strtolower($formatOrRuleset), self::NON_JSON_FORMATS, true)) {
            throw new InvalidUsageException('PHPMD adapter requires the json format outside raw mode.');
        }

        return [$positionals[0], 'json', $formatOrRuleset, ...array_slice($positionals, 2), ...$trailing];
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{positionals: list<string>, trailing: list<string>}
     */
    private function positionalPrefix(array $arguments): array
    {
        $positionals = [];

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '-')) {
                break;
            }

            $positionals[] = $argument;
        }

        return [
            'positionals' => $positionals,
            'trailing' => array_slice($arguments, count($positionals)),
        ];
    }
}
