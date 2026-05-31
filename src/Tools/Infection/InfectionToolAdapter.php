<?php

declare(strict_types=1);

namespace Sift\Tools\Infection;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\Path;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\CliArguments;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class InfectionToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private ?TempFileFactory $tempFileFactory = null,
        private InfectionParser $parser = new InfectionParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'infection';
    }

    protected function description(): string
    {
        return 'Infection mutation testing.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/infection.bat', 'vendor/bin/infection', 'infection'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev infection/infection';
    }

    protected function defaultContext(): string
    {
        return 'mutation';
    }

    #[\Override]
    protected function versionCommand(): array
    {
        return ['--version'];
    }

    #[\Override]
    public function context(CliArguments $arguments, string $cwd): ToolContext
    {
        return new ToolContext(
            toolName: $this->name(),
            subcommand: 'run',
            userArgs: $arguments->toolArguments(),
            cwd: $cwd,
            raw: $arguments->siftOption('raw') === true,
            debug: $arguments->siftOption('debug') === true,
            dryRun: $arguments->has('--dry-run'),
            filter: $arguments->value('--filter'),
            mode: 'mutation',
        );
    }

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        $arguments = $context->userArgs();
        $this->assertRunCommand($arguments);

        if ($context->raw()) {
            return $this->prepareBaseCommand($tool, $context, $config, $arguments);
        }

        $this->assertJsonCanBeWritten($arguments);
        $summaryPath = $this->optionValue($arguments, '--logger-summary-json');
        $temporaryFiles = [];

        if ($summaryPath === null) {
            $tempFile = $this->tempFileFactory()->create('sift-infection-', '.json');
            $summaryPath = $tempFile->path();
            $temporaryFiles[] = $summaryPath;
            $arguments = $this->injectSummaryLogger($arguments, $summaryPath);
        }

        $artifact = $this->artifactPath($summaryPath, $context->cwd());

        return new PreparedCommand(
            tool: $this->name(),
            binary: $tool->binary(),
            arguments: $arguments,
            cwd: $context->cwd(),
            timeout: $config->timeout(),
            temporaryFiles: $temporaryFiles,
            artifacts: ['mutation_summary' => $artifact],
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        try {
            $report = $this->parser->parse(
                path: $this->artifact($command, 'mutation_summary'),
                cwd: $context->cwd(),
                generatedFiles: array_values($command->artifacts()),
            );

            return new NormalizedResult(
                tool: $this->name(),
                status: $this->statusDecider->decide(
                    execution: $execution,
                    findings: $this->thresholdFindings($execution, $report, $command->arguments()),
                ),
                summary: $report->summary(),
                items: $report->items(),
            );
        } finally {
            $this->removeTemporaryFiles($command);
        }
    }

    /**
     * @param list<string> $arguments
     */
    private function assertRunCommand(array $arguments): void
    {
        $first = $arguments[0] ?? null;

        if ($first !== null && ! str_starts_with($first, '-') && $first !== 'run') {
            throw new InvalidUsageException('Infection adapter supports only the "run" command.');
        }
    }

    /**
     * @param list<string> $arguments
     */
    private function assertJsonCanBeWritten(array $arguments): void
    {
        $verbosity = $this->optionValue($arguments, '--log-verbosity');

        if ($verbosity === 'none') {
            throw new InvalidUsageException('Infection adapter requires log verbosity to write JSON summary.');
        }
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function injectSummaryLogger(array $arguments, string $path): array
    {
        if (($arguments[0] ?? null) === 'run') {
            return ['run', '--logger-summary-json', $path, ...array_slice($arguments, 1)];
        }

        return ['--logger-summary-json', $path, ...$arguments];
    }

    /**
     * @param list<string> $arguments
     */
    private function optionValue(array $arguments, string $option): ?string
    {
        foreach ($arguments as $index => $argument) {
            if (str_starts_with($argument, $option . '=')) {
                $value = substr($argument, strlen($option) + 1);

                if ($value === '') {
                    throw new InvalidUsageException(sprintf('Argument "%s" requires a value.', $option));
                }

                return $value;
            }

            if ($argument !== $option) {
                continue;
            }

            $value = $arguments[$index + 1] ?? null;

            if ($value === null || str_starts_with($value, '-')) {
                throw new InvalidUsageException(sprintf('Argument "%s" requires a value.', $option));
            }

            return $value;
        }

        return null;
    }

    private function artifactPath(string $path, string $cwd): string
    {
        return Path::isAbsolute($path) ? Path::normalize($path) : Path::join($cwd, $path);
    }

    private function tempFileFactory(): TempFileFactory
    {
        return $this->tempFileFactory ?? new TempFileFactory(sys_get_temp_dir());
    }

    private function artifact(PreparedCommand $command, string $name): string
    {
        $path = $command->artifacts()[$name] ?? null;

        if (! is_string($path) || $path === '') {
            throw new InvalidUsageException(sprintf('Missing "%s" artifact for Infection.', $name));
        }

        return $path;
    }

    /**
     * @param list<string> $arguments
     */
    private function thresholdFindings(ExecutionResult $execution, InfectionReport $report, array $arguments): int
    {
        $findings = 0;
        $minMsi = $this->floatOption($arguments, '--min-msi');
        $minCoveredMsi = $this->floatOption($arguments, '--min-covered-msi');

        if ($minMsi !== null && $report->msi() < $minMsi) {
            ++$findings;
        }

        if ($minCoveredMsi !== null && $report->coveredMsi() < $minCoveredMsi) {
            ++$findings;
        }

        if ($this->outputContainsMsiFailure($execution)) {
            ++$findings;
        }

        return $findings;
    }

    /**
     * @param list<string> $arguments
     */
    private function floatOption(array $arguments, string $option): ?float
    {
        $value = $this->optionValue($arguments, $option);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^-?(?:\d+(?:\.\d+)?|\.\d+)$/', $value) !== 1) {
            throw new InvalidUsageException(sprintf('Argument "%s" expects a numeric value.', $option));
        }

        return (float) $value;
    }

    private function outputContainsMsiFailure(ExecutionResult $execution): bool
    {
        $output = $execution->stdout() . "\n" . $execution->stderr();

        return preg_match('/minimum required (?:Covered Code )?MSI percentage should be/i', $output) === 1;
    }

    private function removeTemporaryFiles(PreparedCommand $command): void
    {
        foreach ($command->temporaryFiles() as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
