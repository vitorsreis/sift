<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Registry\ToolRegistry;
use Sift\Renderers\JsonRenderer;
use Sift\Renderers\MarkdownRenderer;
use Sift\Runtime\AddService;
use Sift\Runtime\BlockedArgumentsPolicy;
use Sift\Runtime\ComposerCommandPolicy;
use Sift\Runtime\ConfigDocumentManager;
use Sift\Runtime\ConfigLoader;
use Sift\Runtime\FileRunStore;
use Sift\Runtime\InitService;
use Sift\Runtime\PolicyRunner;
use Sift\Runtime\ProcessExecutor;
use Sift\Runtime\ProjectInspector;
use Sift\Runtime\RectorCommandPolicy;
use Sift\Runtime\ResultMetaStamper;
use Sift\Runtime\ResultPayloadFactory;
use Sift\Runtime\ToolEnabledPolicy;
use Sift\Runtime\ToolInstalledPolicy;
use Sift\Runtime\ToolLocator;
use Sift\Runtime\ValidateService;
use Sift\Runtime\ViewService;
use Sift\Sift;
use Sift\Tools\ComposerAuditToolAdapter;
use Sift\Tools\ComposerToolAdapter;
use Sift\Tools\ParatestToolAdapter;
use Sift\Tools\PestToolAdapter;
use Sift\Tools\PhpcsToolAdapter;
use Sift\Tools\PhpstanToolAdapter;
use Sift\Tools\PhpunitToolAdapter;
use Sift\Tools\PintToolAdapter;
use Sift\Tools\PsalmToolAdapter;
use Sift\Tools\RectorToolAdapter;

final class Application
{
    /**
     * @var list<string>
     */
    private array $processLines = [];

    private int $renderedProcessHeight = 0;

    private string $processLineRemainder = '';

    /**
     * @param  list<string>  $argv
     */
    public static function run(array $argv): int
    {
        $toolLocator = new ToolLocator;
        $configLoader = new ConfigLoader;
        $configDocumentManager = new ConfigDocumentManager($configLoader);
        $runStore = new FileRunStore;

        $application = new self(
            new OptionParser,
            self::registry($toolLocator),
            new JsonRenderer,
            new MarkdownRenderer,
            new ProcessExecutor,
            new ResultPayloadFactory,
            new ResultMetaStamper,
            $configLoader,
            $runStore,
            new InitService($toolLocator, $configDocumentManager),
            new AddService($toolLocator, $configDocumentManager),
            new PolicyRunner([
                new ToolEnabledPolicy,
                new BlockedArgumentsPolicy,
                new ToolInstalledPolicy($toolLocator),
                new ComposerCommandPolicy,
                new RectorCommandPolicy,
            ]),
            $toolLocator,
            new ProjectInspector($toolLocator),
            new ValidateService($configLoader),
            new ViewService($runStore),
        );

        try {
            $payload = $application->handle(array_slice($argv, 1));
            $processExitCode = (int) ($payload['_process_exit_code'] ?? 0);

            if (($payload['_passthrough'] ?? false) === true) {
                $stdout = (string) ($payload['_stdout'] ?? '');
                $stderr = (string) ($payload['_stderr'] ?? '');

                if ($stdout !== '') {
                    fwrite(STDOUT, $stdout);
                }

                if ($stderr !== '') {
                    fwrite(STDERR, $stderr);
                }

                return $processExitCode;
            }

            fwrite(STDOUT, $application->render($payload).PHP_EOL);

            return $processExitCode;
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
        private readonly ResultMetaStamper $resultMetaStamper,
        private readonly ConfigLoader $configLoader,
        private readonly FileRunStore $runStore,
        private readonly InitService $initService,
        private readonly AddService $addService,
        private readonly PolicyRunner $policyRunner,
        private readonly ToolLocator $toolLocator,
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
                    'sift [options] init',
                    'sift [options] add [tool]',
                    'sift [options] list',
                    'sift [options] validate',
                    'sift [options] view list',
                    'sift [options] view <run_id> [summary|items|meta|artifacts|extra]',
                    'sift [options] <tool> [tool-args]',
                ],
                'commands' => ['help', 'version', 'init', 'add', 'list', 'validate', 'view', '<tool>'],
                'options' => [
                    '--format=<json|markdown> | -f <json|markdown>',
                    '--size=<compact|normal|fuller> | -s <compact|normal|fuller>',
                    '--raw | -r',
                    '--show-process | --no-show-process',
                    '--pretty | -p | --no-pretty | -P',
                    '--no-history',
                    '--config=<path> | -c <path>',
                ],
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
        $showProcess = ($parsed['show_process'] ?? $config['output']['show_process']) === true;
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

        if ($command === 'add') {
            $add = $this->optionParser->parseAdd($toolArguments);
            $commandConfigPath = $add['config'] ?? $configPath;
            $commandConfig = $this->loadConfigOrDefaults($cwd, $commandConfigPath);
            $format = $add['format'] ?? $parsed['format'] ?? $commandConfig['output']['format'];
            $size = $add['size'] ?? $parsed['size'] ?? $commandConfig['output']['size'];
            $pretty = $add['pretty'] ?? $parsed['pretty'] ?? $commandConfig['output']['pretty'];
            $toolName = $this->resolveAddToolName($cwd, $add['tool'], $commandConfig);

            return [
                ...$this->resultPayloadFactory->commandPayload(
                    $this->addService->add($cwd, $toolName, $this->toolRegistry, $commandConfigPath),
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
                ? $this->viewService->clear($cwd, $commandConfig['history'])
                : ($view['list']
                    ? $this->viewService->list($cwd, $view['limit'], $view['offset'], $commandConfig['history'])
                    : $this->viewService->view(
                        $cwd,
                        (string) $view['run_id'],
                        $view['scope'],
                        $view['limit'],
                        $view['offset'],
                        $commandConfig['history'],
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

        if ($toolArguments === [] && $toolConfig['defaultArgs'] !== []) {
            $toolArguments = $toolConfig['defaultArgs'];
        }

        $this->policyRunner->enforce($cwd, $tool, $toolArguments, $toolConfig);

        if (($parsed['raw'] ?? false) === true) {
            $preparedCommand = $this->prepareRawCommand($cwd, $tool, $toolArguments, $toolConfig);

            try {
                $executionResult = $this->processExecutor->run($preparedCommand);

                return [
                    '_passthrough' => true,
                    '_process_exit_code' => $executionResult->exitCode,
                    '_stdout' => $executionResult->stdout,
                    '_stderr' => $executionResult->stderr,
                ];
            } finally {
                $this->cleanupTempFiles($preparedCommand);
            }
        }

        $context = [
            ...$tool->detectContext($toolArguments),
            'tool_binary' => $toolConfig['toolBinary'],
        ];
        $preparedCommand = $tool->prepare($cwd, $toolArguments, $context);

        try {
            $executionResult = $this->processExecutor->run(
                $preparedCommand,
                $showProcess ? $this->processOutputCallback() : null,
            );
            $result = $this->resultMetaStamper->stamp(
                $tool->parse($executionResult, $preparedCommand, $context),
                $executionResult,
            );
            $runId = null;

            if ($historyEnabled === true) {
                $runId = $this->runStore->put($cwd, $result, $config['history']);
            }

            return [
                ...$this->resultPayloadFactory->forSize($result, $size, $runId),
                '_pretty' => $pretty,
                '_format' => $format,
            ];
        } finally {
            $this->clearProcessOutput();
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

    /**
     * @param  list<string>  $arguments
     * @param  array{enabled: bool, toolBinary: ?string, defaultArgs: list<string>, blockedArgs: list<string>}  $toolConfig
     */
    private function prepareRawCommand(string $cwd, ToolAdapterInterface $tool, array $arguments, array $toolConfig): PreparedCommand
    {
        $configuredBinary = $toolConfig['toolBinary'];
        $candidates = $configuredBinary !== null
            ? [$configuredBinary]
            : $tool->discoveryCandidates();
        $resolved = $this->toolLocator->locate($cwd, $candidates);

        if ($resolved === null) {
            throw UserFacingException::toolNotInstalled($tool->name(), $tool->installHint());
        }

        return new PreparedCommand(
            command: [...$resolved['command_prefix'], ...$arguments],
            cwd: $cwd,
        );
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

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveAddToolName(string $cwd, ?string $toolName, array $config): string
    {
        if (is_string($toolName) && $toolName !== '') {
            return $toolName;
        }

        $items = array_values(array_filter(
            $this->projectInspector->inspect($cwd, $this->toolRegistry, $config),
            static fn (array $item): bool => ($item['installed'] ?? false) === true,
        ));

        usort(
            $items,
            static fn (array $left, array $right): int => strcmp((string) $left['tool'], (string) $right['tool']),
        );

        if ($items === []) {
            throw UserFacingException::invalidUsage(
                'No supported tools were detected for interactive add. Install a supported tool or pass an explicit tool name.',
            );
        }

        $this->renderAddPrompt($items);
        $selection = $this->readAddSelection();

        if ($selection === '') {
            throw UserFacingException::invalidUsage('No tool was selected for `add`.');
        }

        if (ctype_digit($selection)) {
            $index = (int) $selection - 1;

            if (isset($items[$index]['tool']) && is_string($items[$index]['tool'])) {
                return (string) $items[$index]['tool'];
            }
        }

        foreach ($items as $item) {
            if (($item['tool'] ?? null) === $selection) {
                return $selection;
            }
        }

        $supported = implode(', ', array_map(
            static fn (array $item): string => (string) $item['tool'],
            $items,
        ));

        throw UserFacingException::invalidUsage(
            sprintf('Invalid tool selection `%s`. Choose one of: %s.', $selection, $supported),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function renderAddPrompt(array $items): void
    {
        if (! $this->canRenderInteractivePrompt()) {
            return;
        }

        fwrite(STDERR, "Select a tool to add:\n");

        foreach ($items as $index => $item) {
            $path = is_string($item['path'] ?? null) ? str_replace('\\', '/', (string) $item['path']) : null;
            $suffix = $path !== null && $path !== '' ? sprintf(' (%s)', $path) : '';
            fwrite(STDERR, sprintf("  [%d] %s%s\n", $index + 1, (string) $item['tool'], $suffix));
        }

        fwrite(STDERR, 'Enter the number or tool name: ');
        fflush(STDERR);
    }

    private function readAddSelection(): string
    {
        if ($this->hasInteractiveInput()) {
            return trim((string) fgets(STDIN));
        }

        $buffer = stream_get_contents(STDIN);

        if ($buffer === false) {
            return '';
        }

        $line = strtok($buffer, "\r\n");

        return trim($line === false ? '' : $line);
    }

    private function hasInteractiveInput(): bool
    {
        return function_exists('stream_isatty') && stream_isatty(STDIN);
    }

    private function canRenderInteractivePrompt(): bool
    {
        return function_exists('stream_isatty') && stream_isatty(STDERR);
    }

    /**
     * @return callable(string, string): void
     */
    private function processOutputCallback(): callable
    {
        return function (string $type, string $buffer): void {
            unset($type);
            $this->recordProcessOutput($buffer);
        };
    }

    private function recordProcessOutput(string $buffer): void
    {
        $normalized = str_replace("\r", "\n", $buffer);
        $parts = explode("\n", $this->processLineRemainder.$normalized);
        $this->processLineRemainder = array_pop($parts) ?? '';

        $changed = false;

        foreach ($parts as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $this->processLines[] = $line;

            if (count($this->processLines) > 5) {
                array_shift($this->processLines);
            }

            $changed = true;
        }

        if ($changed) {
            $this->renderProcessOutput();
        }
    }

    private function renderProcessOutput(): void
    {
        $this->clearRenderedProcessBlock();

        foreach ($this->processLines as $line) {
            fwrite(STDERR, "\r\033[2K{$line}".PHP_EOL);
        }

        fflush(STDERR);
        $this->renderedProcessHeight = count($this->processLines);
    }

    private function clearProcessOutput(): void
    {
        $remainder = trim($this->processLineRemainder);

        if ($remainder !== '') {
            $this->processLines[] = $remainder;

            if (count($this->processLines) > 5) {
                array_shift($this->processLines);
            }

            $this->processLineRemainder = '';
            $this->renderProcessOutput();
        }

        $this->clearRenderedProcessBlock();
        $this->processLines = [];
        $this->processLineRemainder = '';
    }

    private function clearRenderedProcessBlock(): void
    {
        if ($this->renderedProcessHeight === 0) {
            return;
        }

        fwrite(STDERR, sprintf("\033[%dA", $this->renderedProcessHeight));

        for ($index = 0; $index < $this->renderedProcessHeight; $index++) {
            fwrite(STDERR, "\r\033[2K");

            if ($index < $this->renderedProcessHeight - 1) {
                fwrite(STDERR, "\033[1B");
            }
        }

        if ($this->renderedProcessHeight > 1) {
            fwrite(STDERR, sprintf("\033[%dA", $this->renderedProcessHeight - 1));
        }

        fflush(STDERR);
        $this->renderedProcessHeight = 0;
    }

    private static function registry(ToolLocator $toolLocator): ToolRegistry
    {
        return new ToolRegistry([
            new ComposerAuditToolAdapter($toolLocator),
            new ComposerToolAdapter($toolLocator),
            new ParatestToolAdapter($toolLocator),
            new PestToolAdapter($toolLocator),
            new PhpcsToolAdapter($toolLocator),
            new PintToolAdapter($toolLocator),
            new RectorToolAdapter($toolLocator),
            new PsalmToolAdapter($toolLocator),
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

            if ($parsed['command'] === 'add') {
                $add = $this->optionParser->parseAdd($parsed['arguments']);
                $commandConfig = $this->loadConfigOrDefaults($cwd, $add['config'] ?? $parsed['config']);

                return [
                    'format' => $add['format'] ?? $parsed['format'] ?? $commandConfig['output']['format'],
                    'pretty' => $add['pretty'] ?? $parsed['pretty'] ?? $commandConfig['output']['pretty'],
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
     *   history: array{enabled: bool, max_files: int, path: string},
     *   output: array{format: string, size: string, pretty: bool, show_process: bool},
     *   tools: array<string, array<string, mixed>>
     * }
     */
    private function loadConfigOrDefaults(string $cwd, ?string $configPath = null): array
    {
        try {
            return $this->configLoader->load($cwd, $configPath);
        } catch (UserFacingException) {
            return [
                'history' => ['enabled' => true, 'max_files' => 50, 'path' => '.sift/history'],
                'output' => ['format' => 'json', 'size' => 'normal', 'pretty' => false, 'show_process' => false],
                'tools' => [],
            ];
        }
    }
}
