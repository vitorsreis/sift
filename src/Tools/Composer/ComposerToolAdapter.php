<?php

declare(strict_types=1);

namespace Sift\Tools\Composer;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\CliArguments;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class ComposerToolAdapter extends AbstractCliToolAdapter
{
    /**
     * @var list<string>
     */
    private const array ALLOWED_SUBCOMMANDS = ['audit', 'licenses', 'outdated', 'show', 'validate'];

    /**
     * @var list<string>
     */
    private const array OPTIONS_WITH_VALUES = ['--format', '-f', '--working-dir', '-d'];

    public function __construct(
        private ComposerAuditParser $auditParser = new ComposerAuditParser(),
        private ComposerLicensesParser $licensesParser = new ComposerLicensesParser(),
        private ComposerPackagesParser $packagesParser = new ComposerPackagesParser(),
        private ComposerValidateParser $validateParser = new ComposerValidateParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'composer';
    }

    protected function description(): string
    {
        return 'Composer package metadata and audit runner.';
    }

    protected function binaryCandidates(): array
    {
        return ['composer.cmd', 'composer.bat', 'composer'];
    }

    protected function installHint(): string
    {
        return 'Install Composer from https://getcomposer.org/download/.';
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
    public function context(CliArguments $arguments, string $cwd): ToolContext
    {
        $base = parent::context($arguments, $cwd);
        $command = $this->command($base->userArgs(), defaultAudit: ! $base->raw(), validate: false);

        return new ToolContext(
            toolName: $this->name(),
            subcommand: $command['subcommand'],
            userArgs: $base->userArgs(),
            cwd: $base->cwd(),
            config: $base->config(),
            outputPreferences: $base->outputPreferences(),
            raw: $base->raw(),
            debug: $base->debug(),
            repair: $base->repair(),
            dryRun: $base->dryRun(),
            filter: $base->filter(),
            coverage: $base->coverage(),
            coverageMin: $base->coverageMin(),
            mode: $command['mode'],
            warnings: $base->warnings(),
        );
    }

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        if ($context->raw()) {
            $this->command($context->userArgs(), defaultAudit: false, validate: true);

            return $this->prepareBaseCommand($tool, $context, $config, $context->userArgs());
        }

        $command = $this->command($context->userArgs(), defaultAudit: true, validate: true);

        return $this->prepareBaseCommand(
            tool: $tool,
            context: $context,
            config: $config,
            arguments: $this->jsonArguments($command),
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        $subcommand = $context->subcommand() ?? $this->command($command->arguments(), defaultAudit: true, validate: false)['subcommand'];

        $report = match ($subcommand) {
            'audit' => $this->auditParser->parse($execution->stdout(), $execution->stderr()),
            'licenses' => $this->licensesParser->parse($execution->stdout(), $execution->stderr()),
            'outdated', 'show' => $this->packagesParser->parse($execution->stdout(), $execution->stderr()),
            'validate' => $this->validateParser->parse($execution),
            default => throw new InvalidUsageException('Composer adapter supports only audit, licenses, outdated, show and validate.'),
        };

        return new NormalizedResult(
            tool: $this->name(),
            status: $this->statusDecider->decide($execution, findings: $report->findings()),
            summary: $report->summary(),
            items: $report->items(),
            extra: $report->extra(),
        );
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{subcommand: string|null, position: int|null, arguments: list<string>, mode: string|null}
     */
    private function command(array $arguments, bool $defaultAudit, bool $validate): array
    {
        $position = $this->subcommandPosition($arguments);
        $subcommand = $position === null ? null : $arguments[$position];

        if ($subcommand === null && $defaultAudit) {
            $subcommand = 'audit';
        }

        if ($validate && ($subcommand === null || ! in_array($subcommand, self::ALLOWED_SUBCOMMANDS, true))) {
            throw new InvalidUsageException('Composer adapter supports only audit, licenses, outdated, show and validate.');
        }

        return [
            'subcommand' => $subcommand,
            'position' => $position,
            'arguments' => $position === null ? $arguments : $this->withoutIndex($arguments, $position),
            'mode' => $subcommand === 'show' && in_array('--outdated', $arguments, true) ? 'outdated' : null,
        ];
    }

    /**
     * @param list<string> $arguments
     */
    private function subcommandPosition(array $arguments): ?int
    {
        $skipNext = false;

        foreach ($arguments as $index => $argument) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            if ($argument === '--') {
                return isset($arguments[$index + 1]) ? $index + 1 : null;
            }

            if ($this->optionHasInlineValue($argument)) {
                continue;
            }

            if (in_array($argument, self::OPTIONS_WITH_VALUES, true)) {
                $skipNext = true;
                continue;
            }

            if (str_starts_with($argument, '-')) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function optionHasInlineValue(string $argument): bool
    {
        foreach (self::OPTIONS_WITH_VALUES as $option) {
            if (str_starts_with($argument, $option . '=')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function withoutIndex(array $arguments, int $position): array
    {
        unset($arguments[$position]);

        return array_values($arguments);
    }

    /**
     * @param array{subcommand: string|null, position: int|null, arguments: list<string>, mode: string|null} $command
     *
     * @return list<string>
     */
    private function jsonArguments(array $command): array
    {
        $subcommand = $command['subcommand'];

        if (! is_string($subcommand)) {
            throw new InvalidUsageException('Composer adapter supports only audit, licenses, outdated, show and validate.');
        }

        $commandArguments = $this->withoutArguments($command['arguments'], [
            '--no-progress',
            '--ansi',
            '--no-ansi',
            '-n',
            '--no-interaction',
            '-q',
            '--quiet',
        ]);
        $quietArguments = ['--no-ansi', '--no-interaction'];

        if ($subcommand === 'validate') {
            return [$subcommand, ...$quietArguments, ...$commandArguments];
        }

        $format = $this->optionValue($commandArguments, '--format', '-f');

        if ($format !== null && strtolower($format) !== 'json') {
            throw new InvalidUsageException('Composer adapter requires JSON output outside raw mode.');
        }

        $arguments = [$subcommand, ...$quietArguments];

        if ($format === null) {
            $arguments[] = '--format=json';
        }

        return [...$arguments, ...$commandArguments];
    }

    /**
     * @param list<string> $arguments
     */
    private function optionValue(array $arguments, string ...$options): ?string
    {
        foreach ($arguments as $index => $argument) {
            foreach ($options as $option) {
                if (str_starts_with($argument, $option . '=')) {
                    return substr($argument, strlen($option) + 1);
                }

                if ($argument === $option) {
                    $value = $arguments[$index + 1] ?? null;

                    return is_string($value) ? $value : null;
                }
            }
        }

        return null;
    }
}
