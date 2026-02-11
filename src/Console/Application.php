<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Registry\ToolRegistry;
use Sift\Renderers\JsonRenderer;
use Sift\Renderers\MarkdownRenderer;
use Sift\Runtime\ConfigLoader;
use Sift\Runtime\FileRunStore;
use Sift\Runtime\InitService;
use Sift\Runtime\ProcessExecutor;
use Sift\Runtime\ProjectInspector;
use Sift\Runtime\ResultPayloadFactory;
use Sift\Runtime\ToolLocator;
use Sift\Runtime\ValidateService;
use Sift\Runtime\ViewService;
use Sift\Sift;
use Sift\Tools\ComposerAuditToolAdapter;
use Sift\Tools\PhpstanToolAdapter;
use Sift\Tools\PhpunitToolAdapter;
use Sift\Tools\PintToolAdapter;

final class Application
{
    /**
     * @param  list<string>  $argv
     */
    public static function run(array $argv): int
    {
        $application = new self(
            new OptionParser,
            self::registry(new ToolLocator),
            new JsonRenderer,
            new MarkdownRenderer,
            new ProcessExecutor,
            new ResultPayloadFactory,
            new ConfigLoader,
            new FileRunStore,
            new InitService(new ToolLocator),
            new ProjectInspector(new ToolLocator),
            new ValidateService(new ConfigLoader),
            new ViewService(new FileRunStore),
        );

        try {
            $payload = $application->handle(array_slice($argv, 1));
            fwrite(STDOUT, $application->render($payload).PHP_EOL);

            return 0;
        } catch (UserFacingException $exception) {
            $payload = $exception->payload();
            $preferences = $application->resolveRenderPreferences(array_slice($argv, 1));
            $payload['_pretty'] = $preferences['pretty'];
            $payload['_format'] = $preferences['format'];
            fwrite(STDERR, $application->render($payload).PHP_EOL);

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
        private readonly ConfigLoader $configLoader,
        private readonly FileRunStore $runStore,
        private readonly InitService $initService,
        private readonly ProjectInspector $projectInspector,
        private readonly ValidateService $validateService,
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
        $cwd = getcwd() ?: '.';
        $safeConfig = $this->loadConfigOrDefaults($cwd);

        if (in_array($command, ['--version', '-V', 'version'], true)) {
            return [
                'status' => 'ok',
                'tool' => 'sift',
                'version' => Sift::VERSION,
                '_pretty' => $parsed['pretty'] ?? $safeConfig['output']['pretty'],
                '_format' => $parsed['format'] ?? $safeConfig['output']['format'],
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
                    'sift validate',
                    'sift <tool> [tool-args]',
                ],
                'commands' => ['help', 'version', 'init', 'list', 'validate', 'view', '<tool>'],
                '_pretty' => $parsed['pretty'] ?? $safeConfig['output']['pretty'],
                '_format' => $parsed['format'] ?? $safeConfig['output']['format'],
            ];
        }

        $config = in_array($command, ['init', 'view'], true)
            ? $safeConfig
            : $this->configLoader->load($cwd);
        $format = $parsed['format'] ?? $config['output']['format'];
        $size = $parsed['size'] ?? $config['output']['size'];
        $pretty = $parsed['pretty'] ?? $config['output']['pretty'];

        if ($command === 'init') {
            $init = $this->optionParser->parseInit($toolArguments);
            $format = $init['format'] ?? $format;
            $size = $init['size'] ?? $size;
            $pretty = $init['pretty'] ?? $pretty;

            return [
                ...$this->resultPayloadFactory->commandPayload(
                    $this->initService->initialize($cwd, $init['force'], $this->toolRegistry),
                    $size,
                ),
                '_pretty' => $pretty,
                '_format' => $format,
            ];
        }

        if ($command === 'list') {
            $items = $this->projectInspector->inspect(
                $cwd,
                $this->toolRegistry,
                $config,
            );

            return [
                ...$this->resultPayloadFactory->commandPayload([
                    'status' => 'ok',
                    'tool' => 'sift',
                    'tools' => $items,
                ], $size),
                '_pretty' => $pretty,
                '_format' => $format,
            ];
        }

        if ($command === 'validate') {
            $validate = $this->optionParser->parseValidate($toolArguments);
            $format = $validate['format'] ?? $format;
            $size = $validate['size'] ?? $size;
            $pretty = $validate['pretty'] ?? $pretty;

            return [
                ...$this->resultPayloadFactory->commandPayload(
                    $this->validateService->validate($cwd),
                    $size,
                ),
                '_pretty' => $pretty,
                '_format' => $format,
            ];
        }

        if ($command === 'view') {
            $view = $this->optionParser->parseView($toolArguments);
            $format = $view['format'] ?? $format;
            $pretty = $view['pretty'] ?? $pretty;

            $payload = $view['clear']
                ? $this->viewService->clear($cwd)
                : ($view['list']
                    ? $this->viewService->list($cwd, $view['limit'], $view['offset'])
                    : $this->viewService->view(
                        $cwd,
                        (string) $view['run_id'],
                        $view['scope'],
                        $view['limit'],
                        $view['offset'],
                    ));

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

        $toolConfig = $this->configLoader->tool($config, $command);

        if ($toolConfig['enabled'] !== true) {
            throw UserFacingException::toolDisabled($command);
        }

        if ($toolArguments === [] && $toolConfig['defaultArgs'] !== []) {
            $toolArguments = $toolConfig['defaultArgs'];
        }

        $context = $tool->detectContext($toolArguments);
        $preparedCommand = $tool->prepare($cwd, $toolArguments, $context);

        try {
            $executionResult = $this->processExecutor->run($preparedCommand);
            $result = $tool->parse($executionResult, $preparedCommand, $context);
            $runId = null;

            if ($config['history']['enabled'] === true) {
                $runId = $this->runStore->put($cwd, $result);
            }

            return [
                ...$this->resultPayloadFactory->forSize($result, $size, $runId),
                '_pretty' => $pretty,
                '_format' => $format,
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

    private static function registry(ToolLocator $toolLocator): ToolRegistry
    {
        return new ToolRegistry([
            new ComposerAuditToolAdapter($toolLocator),
            new PintToolAdapter($toolLocator),
            new PhpstanToolAdapter($toolLocator),
            new PhpunitToolAdapter($toolLocator),
        ]);
    }

    /**
     * @param  list<string>  $arguments
     * @return array{format: string, pretty: bool}
     */
    private function resolveRenderPreferences(array $arguments): array
    {
        try {
            $parsed = $this->optionParser->parse($arguments);
        } catch (UserFacingException) {
            return [
                'format' => 'json',
                'pretty' => false,
            ];
        }

        $cwd = getcwd() ?: '.';
        $config = $this->loadConfigOrDefaults($cwd);

        $format = $parsed['format'] ?? $config['output']['format'];
        $pretty = $parsed['pretty'] ?? $config['output']['pretty'];

        try {
            return match ($parsed['command']) {
                'init' => [
                    'format' => $this->optionParser->parseInit($parsed['arguments'])['format'] ?? $format,
                    'pretty' => $this->optionParser->parseInit($parsed['arguments'])['pretty'] ?? $pretty,
                ],
                'validate' => [
                    'format' => $this->optionParser->parseValidate($parsed['arguments'])['format'] ?? $format,
                    'pretty' => $this->optionParser->parseValidate($parsed['arguments'])['pretty'] ?? $pretty,
                ],
                'view' => [
                    'format' => $this->optionParser->parseView($parsed['arguments'])['format'] ?? $format,
                    'pretty' => $this->optionParser->parseView($parsed['arguments'])['pretty'] ?? $pretty,
                ],
                default => [
                    'format' => $format,
                    'pretty' => $pretty,
                ],
            };
        } catch (UserFacingException) {
            return [
                'format' => $format,
                'pretty' => $pretty,
            ];
        }
    }

    /**
     * @return array{
     *   history: array{enabled: bool},
     *   output: array{format: string, size: string, pretty: bool},
     *   tools: array<string, array<string, mixed>>
     * }
     */
    private function loadConfigOrDefaults(string $cwd): array
    {
        try {
            return $this->configLoader->load($cwd);
        } catch (UserFacingException) {
            return [
                'history' => ['enabled' => true],
                'output' => ['format' => 'json', 'size' => 'normal', 'pretty' => false],
                'tools' => [],
            ];
        }
    }
}
