<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Core\NormalizedResult;
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
            new InitService(new ToolLocator, new ConfigLoader),
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
        $configPath = $parsed['config'];
        $cwd = getcwd() ?: '.';
        $safeConfig = $this->loadConfigOrDefaults($cwd, $configPath);

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
            : $this->configLoader->load($cwd, $configPath);
        $format = $parsed['format'] ?? $config['output']['format'];
        $size = $parsed['size'] ?? $config['output']['size'];
        $pretty = $parsed['pretty'] ?? $config['output']['pretty'];
        $historyEnabled = $parsed['history'] ?? $config['history']['enabled'];

        if ($command === 'init') {
            $init = $this->optionParser->parseInit($toolArguments);
            $commandConfigPath = $init['config'] ?? $configPath;
            $commandConfig = $this->loadConfigOrDefaults($cwd, $commandConfigPath);
            $format = $init['format'] ?? $parsed['format'] ?? $commandConfig['output']['format'];
            $size = $init['size'] ?? $parsed['size'] ?? $commandConfig['output']['size'];
            $pretty = $init['pretty'] ?? $parsed['pretty'] ?? $commandConfig['output']['pretty'];

            return [
                ...$this->resultPayloadFactory->commandPayload(
                    $this->initService->initialize($cwd, $init['force'], $this->toolRegistry, $commandConfigPath),
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
            $commandConfigPath = $validate['config'] ?? $configPath;
            $commandConfig = $this->configLoader->load($cwd, $commandConfigPath);
            $format = $validate['format'] ?? $parsed['format'] ?? $commandConfig['output']['format'];
            $size = $validate['size'] ?? $parsed['size'] ?? $commandConfig['output']['size'];
            $pretty = $validate['pretty'] ?? $parsed['pretty'] ?? $commandConfig['output']['pretty'];

            return [
                ...$this->resultPayloadFactory->commandPayload(
                    $this->validateService->validate($cwd, $commandConfigPath),
                    $size,
                ),
                '_pretty' => $pretty,
                '_format' => $format,
            ];
        }

        if ($command === 'view') {
            $view = $this->optionParser->parseView($toolArguments);
            $commandConfigPath = $view['config'] ?? $configPath;
            $commandConfig = $this->loadConfigOrDefaults($cwd, $commandConfigPath);
            $format = $view['format'] ?? $parsed['format'] ?? $commandConfig['output']['format'];
            $pretty = $view['pretty'] ?? $parsed['pretty'] ?? $commandConfig['output']['pretty'];

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
            $result = $this->stampResult(
                $tool->parse($executionResult, $preparedCommand, $context),
            );
            $runId = null;

            if ($historyEnabled === true) {
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

    private function stampResult(NormalizedResult $result): NormalizedResult
    {
        if (is_string($result->meta['created_at'] ?? null) && $result->meta['created_at'] !== '') {
            return $result;
        }

        return $result->withMeta([
            ...$result->meta,
            'created_at' => gmdate('c'),
        ]);
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
        $config = $this->loadConfigOrDefaults($cwd, $parsed['config']);

        $format = $parsed['format'] ?? $config['output']['format'];
        $pretty = $parsed['pretty'] ?? $config['output']['pretty'];

        try {
            if ($parsed['command'] === 'init') {
                $init = $this->optionParser->parseInit($parsed['arguments']);
                $commandConfig = $this->loadConfigOrDefaults($cwd, $init['config'] ?? $parsed['config']);

                return [
                    'format' => $init['format'] ?? $parsed['format'] ?? $commandConfig['output']['format'],
                    'pretty' => $init['pretty'] ?? $parsed['pretty'] ?? $commandConfig['output']['pretty'],
                ];
            }

            if ($parsed['command'] === 'validate') {
                $validate = $this->optionParser->parseValidate($parsed['arguments']);
                $commandConfig = $this->loadConfigOrDefaults($cwd, $validate['config'] ?? $parsed['config']);

                return [
                    'format' => $validate['format'] ?? $parsed['format'] ?? $commandConfig['output']['format'],
                    'pretty' => $validate['pretty'] ?? $parsed['pretty'] ?? $commandConfig['output']['pretty'],
                ];
            }

            if ($parsed['command'] === 'view') {
                $view = $this->optionParser->parseView($parsed['arguments']);
                $commandConfig = $this->loadConfigOrDefaults($cwd, $view['config'] ?? $parsed['config']);

                return [
                    'format' => $view['format'] ?? $parsed['format'] ?? $commandConfig['output']['format'],
                    'pretty' => $view['pretty'] ?? $parsed['pretty'] ?? $commandConfig['output']['pretty'],
                ];
            }

            return [
                'format' => $format,
                'pretty' => $pretty,
            ];
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
    private function loadConfigOrDefaults(string $cwd, ?string $configPath = null): array
    {
        try {
            return $this->configLoader->load($cwd, $configPath);
        } catch (UserFacingException) {
            return [
                'history' => ['enabled' => true],
                'output' => ['format' => 'json', 'size' => 'normal', 'pretty' => false],
                'tools' => [],
            ];
        }
    }
}
