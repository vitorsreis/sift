<?php

declare(strict_types=1);

use Sift\Console\Commands\CommandHandler;
use Sift\Console\Commands\HelpCommand;
use Sift\Console\Commands\ToolsListCommand;
use Sift\Console\Commands\VersionCommand;

it('exposes built-in commands through the shared handler contract', function (): void {
    expect(new HelpCommand())->toBeInstanceOf(CommandHandler::class);
    expect(new VersionCommand())->toBeInstanceOf(CommandHandler::class);
    expect(new ToolsListCommand())->toBeInstanceOf(CommandHandler::class);
});
