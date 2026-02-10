<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Exceptions\UserFacingException;
use Sift\Renderers\JsonRenderer;
use Sift\Sift;

final class Application
{
    /**
     * @param  list<string>  $argv
     */
    public static function run(array $argv): int
    {
        $application = new self(
            new OptionParser,
            new JsonRenderer,
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
        private readonly JsonRenderer $jsonRenderer,
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
            ];
        }

        if ($command === 'list') {
            return [
                'status' => 'ok',
                'tool' => 'sift',
                'tools' => [],
                '_pretty' => $parsed['pretty'],
            ];
        }

        throw UserFacingException::unsupportedTool($command);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload): string
    {
        $pretty = (bool) ($payload['_pretty'] ?? false);
        unset($payload['_pretty']);

        return $this->jsonRenderer->render($payload, $pretty);
    }
}
