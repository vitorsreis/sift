<?php

declare(strict_types=1);

namespace Sift\Console;

final readonly class CommandRouter
{
    /**
     * @param array<string, string> $handlers
     */
    private function __construct(
        private array $handlers,
    ) {}

    public static function forSift(): self
    {
        return new self([
            'help' => 'help',
            'version' => 'version',
            'init' => 'init',
            'validate' => 'validate',
            'skills' => 'skills.help',
            'skills list' => 'skills.list',
            'skills add' => 'skills.add',
            'skills find' => 'skills.find',
            'skills init' => 'skills.init',
            'skills remove' => 'skills.remove',
            'skills update' => 'skills.update',
            'tools list' => 'tools.list',
            'history list' => 'history.list',
            'history view' => 'history.view',
            'history clear' => 'history.clear',
            'history remove' => 'history.remove',
            'run' => 'run.tool',
        ]);
    }

    public function route(CliRequest $request): CommandRoute
    {
        $handler = $this->handlers[$request->command()] ?? null;

        if ($handler === null) {
            throw new InvalidUsageException(sprintf('Unknown command "%s".', $request->command()));
        }

        if ($handler === 'run.tool' && $request->arguments() === []) {
            throw new InvalidUsageException('Missing tool name.');
        }

        return new CommandRoute(
            handler: $handler,
            arguments: $request->arguments(),
            options: $request->options(),
            globalOptions: $request->globalOptions(),
        );
    }
}
