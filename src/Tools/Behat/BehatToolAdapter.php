<?php

declare(strict_types=1);

namespace Sift\Tools\Behat;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\ExecutionResult;
use Sift\Core\ItemType;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Core\RunStatus;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\CliArguments;
use Sift\Tools\StatusDecider;
use Sift\Tools\ToolContext;

final readonly class BehatToolAdapter extends AbstractCliToolAdapter
{
    public function __construct(
        private ?TempFileFactory $tempFileFactory = null,
        private BehatParser $parser = new BehatParser(),
        private StatusDecider $statusDecider = new StatusDecider(),
    ) {}

    protected function name(): string
    {
        return 'behat';
    }

    protected function description(): string
    {
        return 'Behat behavior-driven test runner.';
    }

    protected function binaryCandidates(): array
    {
        return ['vendor/bin/behat.bat', 'vendor/bin/behat', 'behat'];
    }

    protected function installHint(): string
    {
        return 'composer require --dev behat/behat';
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

    #[\Override]
    public function context(CliArguments $arguments, string $cwd): ToolContext
    {
        return new ToolContext(
            toolName: $this->name(),
            userArgs: $arguments->toolArguments(),
            cwd: $cwd,
            raw: $arguments->siftOption('raw') === true,
            debug: $arguments->siftOption('debug') === true,
            filter: $arguments->value('--name'),
            mode: 'bdd',
        );
    }

    #[\Override]
    public function prepare(LocatedTool $tool, ToolContext $context, ToolConfig $config): PreparedCommand
    {
        if ($context->raw()) {
            return $this->prepareBaseCommand($tool, $context, $config, $context->userArgs());
        }

        $this->assertOutputAvailable($context->userArgs());
        $report = $this->tempFileFactory()->create('sift-behat-', '.json');
        $userArguments = $this->withoutArguments($context->userArgs(), [
            '--colors',
            '--no-colors',
            '-n',
            '--no-interaction',
        ]);
        $arguments = [
            '--format=json',
            '--out=' . $report->path(),
            '--no-colors',
            '--no-interaction',
        ];

        return new PreparedCommand(
            tool: $this->name(),
            binary: $tool->binary(),
            arguments: [...$arguments, ...$userArguments],
            cwd: $context->cwd(),
            timeout: $config->timeout(),
            temporaryFiles: [$report->path()],
            artifacts: ['behat_json' => $report->path()],
        );
    }

    public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
    {
        try {
            $path = $command->artifacts()['behat_json'] ?? '';

            if (! $execution->successful() && (! is_file($path) || filesize($path) === 0)) {
                return $this->nativeExecutionError($execution);
            }

            $report = $this->parser->parse($path, $context->cwd(), array_values($command->artifacts()));

            return new NormalizedResult(
                tool: $this->name(),
                status: $this->statusDecider->decide($execution, findings: $report->findings()),
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
    private function assertOutputAvailable(array $arguments): void
    {
        foreach ($arguments as $argument) {
            if (in_array($argument, ['-f', '--format', '-o', '--out'], true)
                || preg_match('/^-(?:f|o).+/', $argument) === 1
                || str_starts_with($argument, '--format=')
                || str_starts_with($argument, '--out=')) {
                throw new InvalidUsageException('Behat adapter controls JSON format and output; use --raw for custom formatters.');
            }
        }
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
                'message' => $message === '' ? 'Behat exited before writing its JSON report.' : $message,
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
