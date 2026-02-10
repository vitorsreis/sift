<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Exceptions\UserFacingException;
use Sift\Registry\ToolRegistry;
use Sift\Renderers\JsonRenderer;
use Sift\Renderers\MarkdownRenderer;
use Sift\Runtime\ProcessExecutor;
use Sift\Runtime\ResultPayloadFactory;
use Sift\Runtime\ToolLocator;
use Sift\Sift;
use Sift\Tools\PhpstanToolAdapter;

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
            ]),
            new JsonRenderer,
            new MarkdownRenderer,
            new ProcessExecutor,
            new ResultPayloadFactory,
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
                    'sift list',
                    'sift <tool> [tool-args]',
                ],
                'commands' => ['help', 'version', 'list', '<tool>'],
                '_pretty' => $parsed['pretty'],
                '_format' => $parsed['format'],
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

        $tool = $this->toolRegistry->find($command);

        if ($tool === null) {
            throw UserFacingException::unsupportedTool($command);
        }

        $context = $tool->detectContext($toolArguments);
        $preparedCommand = $tool->prepare(getcwd() ?: '.', $toolArguments, $context);
        $executionResult = $this->processExecutor->run($preparedCommand);
        $result = $tool->parse($executionResult, $preparedCommand, $context);

        return [
            ...$this->resultPayloadFactory->forSize($result, $parsed['size']),
            '_pretty' => $parsed['pretty'],
            '_format' => $parsed['format'],
        ];
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
}
