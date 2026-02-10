<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Exceptions\UserFacingException;
use Sift\Registry\ToolRegistry;
use Sift\Renderers\JsonRenderer;
use Sift\Renderers\MarkdownRenderer;
use Sift\Runtime\FileRunStore;
use Sift\Runtime\InitService;
use Sift\Runtime\ProcessExecutor;
use Sift\Runtime\ResultPayloadFactory;
use Sift\Runtime\ToolLocator;
use Sift\Runtime\ViewService;
use Sift\Sift;
use Sift\Tools\PhpstanToolAdapter;
use Sift\Tools\PhpunitToolAdapter;

final class Application
{
    /**
     * @param  list<string>  $argv
     */
    public static function run(array $argv): int
    {
        $application = new self(
            new OptionParser,
            new ToolRegistry([
                new PhpstanToolAdapter(new ToolLocator),
                new PhpunitToolAdapter(new ToolLocator),
            ]),
            new JsonRenderer,
            new MarkdownRenderer,
            new ProcessExecutor,
            new ResultPayloadFactory,
            new FileRunStore,
            new InitService(new ToolLocator),
            new ViewService(new FileRunStore),
        );

        try {
            $payload = $application->handle(array_slice($argv, 1));
            fwrite(STDOUT, $application->render($payload).PHP_EOL);

            return 0;
        } catch (UserFacingException $exception) {
            fwrite(STDERR, $application->render($exception->payload()).PHP_EOL);

            return $exception->processExitCode();
        }
    }

    public function __construct(
        private readonly OptionParser $optionParser,
        private readonly ToolRegistry $toolRegistry,
        private readonly JsonRenderer $jsonRenderer,
        private readonly MarkdownRenderer $markdownRenderer,
        private readonly ProcessExecutor $processExecutor,
        private readonly ResultPayloadFactory $resultPayloadFactory,
        private readonly FileRunStore $runStore,
        private readonly InitService $initService,
        private readonly ViewService $viewService,
    ) {}

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        $parsed = $this->optionParser->parse($arguments);
        $command = $parsed['command'];
        $toolArguments = $parsed['arguments'];

        if (in_array($command, ['--version', '-V', 'version'], true)) {
            return [
                'status' => 'ok',
                'tool' => 'sift',
                'version' => Sift::VERSION,
                '_pretty' => $parsed['pretty'],
                '_format' => $parsed['format'],
            ];
        }

        if (in_array($command, ['--help', '-h', 'help'], true)) {
            return [
                'status' => 'ok',
                'tool' => 'sift',
                'usage' => [
                    'sift help',
                    'sift version',
                    'sift init',
                    'sift list',
                    'sift <tool> [tool-args]',
                ],
                'commands' => ['help', 'version', 'init', 'list', 'view', '<tool>'],
                '_pretty' => $parsed['pretty'],
                '_format' => $parsed['format'],
            ];
        }

        if ($command === 'init') {
            $init = $this->optionParser->parseInit($toolArguments);
            $format = $init['format'] ?? $parsed['format'];
            $size = $init['size'] ?? $parsed['size'];
            $pretty = $init['pretty'] ?? $parsed['pretty'];

            return [
                ...$this->resultPayloadFactory->commandPayload(
                    $this->initService->initialize(getcwd() ?: '.', $init['force'], $this->toolRegistry),
                    $size,
                ),
                '_pretty' => $pretty,
                '_format' => $format,
            ];
        }

        if ($command === 'list') {
            return [
                ...$this->resultPayloadFactory->commandPayload([
                    'status' => 'ok',
                    'tool' => 'sift',
                    'tools' => $this->toolRegistry->names(),
                ], $parsed['size']),
                '_pretty' => $parsed['pretty'],
                '_format' => $parsed['format'],
            ];
        }

        if ($command === 'view') {
            $view = $this->optionParser->parseView($toolArguments);
            $format = $view['format'] ?? $parsed['format'];
            $pretty = $view['pretty'] ?? $parsed['pretty'];

            $payload = $view['list']
                ? $this->viewService->list(getcwd() ?: '.', $view['limit'], $view['offset'])
                : $this->viewService->view(
                    getcwd() ?: '.',
                    (string) $view['run_id'],
                    $view['scope'],
                    $view['limit'],
                    $view['offset'],
                );

            return [
                ...$payload,
                '_pretty' => $pretty,
                '_format' => $format,
            ];
        }

        $tool = $this->toolRegistry->find($command);

        if ($tool === null) {
            throw UserFacingException::unsupportedTool($command);
        }

        $context = $tool->detectContext($toolArguments);
        $preparedCommand = $tool->prepare(getcwd() ?: '.', $toolArguments, $context);

        try {
            $executionResult = $this->processExecutor->run($preparedCommand);
            $result = $tool->parse($executionResult, $preparedCommand, $context);
            $runId = $this->runStore->put(getcwd() ?: '.', $result);

            return [
                ...$this->resultPayloadFactory->forSize($result, $parsed['size'], $runId),
                '_pretty' => $parsed['pretty'],
                '_format' => $parsed['format'],
            ];
        } finally {
            $this->cleanupTempFiles($preparedCommand);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload): string
    {
        $pretty = (bool) ($payload['_pretty'] ?? false);
        $format = (string) ($payload['_format'] ?? 'json');
        unset($payload['_pretty']);
        unset($payload['_format']);

        if ($format === 'markdown') {
            return $this->markdownRenderer->render($payload, $pretty);
        }

        return $this->jsonRenderer->render($payload, $pretty);
    }

    private function cleanupTempFiles(PreparedCommand $preparedCommand): void
    {
        $tempFiles = $preparedCommand->metadata['temp_files'] ?? null;

        if (! is_array($tempFiles)) {
            return;
        }

        foreach ($tempFiles as $tempFile) {
            if (! is_string($tempFile) || $tempFile === '' || ! is_file($tempFile)) {
                continue;
            }

            @unlink($tempFile);
        }
    }
}
