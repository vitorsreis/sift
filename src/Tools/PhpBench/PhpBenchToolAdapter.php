<?php

declare(strict_types=1);

namespace Sift\Tools\PhpBench;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\ItemType;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\Path;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\CliArguments;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class PhpBenchToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private ?TempFileFactory $tempFileFactory = null,
        private PhpBenchParser $parser = new PhpBenchParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'phpbench';
    }

    protected function description(): string
    {
        return 'PHPBench benchmark runner.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/phpbench.bat', 'vendor/bin/phpbench', 'phpbench'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev phpbench/phpbench';
    }

    protected function defaultContext(): string
    {
        return 'benchmark';
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
            mode: 'benchmark',
        );
    }

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        $arguments = $this->withoutRunPseudoSubcommand($context->userArgs());

        if ($context->raw()) {
            return $this->prepareBaseCommand($tool, $context, $config, ['run', ...$arguments]);
        }

        $dumpPath = $this->optionValue($arguments, '--dump-file');
        $temporaryFiles = [];

        if ($dumpPath === null) {
            $dump = $this->tempFileFactory()->create('sift-phpbench-', '.xml');
            $dumpPath = $dump->path();
            $temporaryFiles[] = $dumpPath;
            $arguments[] = '--dump-file=' . $dumpPath;
        }

        $arguments = $this->withoutArguments($arguments, [
            '--silent',
            '--ansi',
            '--no-ansi',
            '-n',
            '--no-interaction',
            '-q',
            '--quiet',
        ]);

        return new PreparedCommand(
            tool: $this->name(),
            binary: $tool->binary(),
            arguments: ['run', '--silent', '--no-ansi', '--no-interaction', ...$arguments],
            cwd: $context->cwd(),
            timeout: $config->timeout(),
            temporaryFiles: $temporaryFiles,
            artifacts: [
                'phpbench_xml' => Path::isAbsolute($dumpPath) ? Path::normalize($dumpPath) : Path::join($context->cwd(), $dumpPath),
            ],
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        try {
            $path = $command->artifacts()['phpbench_xml'] ?? '';

            if (! $execution->successful() && (! is_file($path) || filesize($path) === 0)) {
                return $this->nativeExecutionError($execution);
            }

            $report = $this->parser->parse($path, $context->cwd(), array_values($command->artifacts()));

            return new NormalizedResult(
                tool: $this->name(),
                status: $this->statusDecider->decide(
                    execution: $execution,
                    findings: $report->findings(),
                    errorCode: $report->errors() > 0 ? ErrorCode::ProcessFailed : null,
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
     *
     * @return list<string>
     */
    private function withoutRunPseudoSubcommand(array $arguments): array
    {
        return ($arguments[0] ?? null) === 'run' ? array_slice($arguments, 1) : $arguments;
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

    private function tempFileFactory(): TempFileFactory
    {
        return $this->tempFileFactory ?? new TempFileFactory(sys_get_temp_dir());
    }

    private function nativeExecutionError(ExecutionResult $execution): NormalizedResult
    {
        $message = trim($execution->stderr()) !== '' ? trim($execution->stderr()) : trim($execution->stdout());

        return new NormalizedResult(
            tool: $this->name(),
            status: RunStatus::Error,
            summary: ['exit_code' => $execution->exitCode()],
            items: [[
                'type' => ItemType::Error->value,
                'message' => $message === '' ? 'PHPBench exited before writing its XML dump.' : $message,
            ]],
            extra: ['stdout' => trim($execution->stdout()), 'stderr' => trim($execution->stderr())],
        );
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
