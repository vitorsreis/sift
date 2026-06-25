<?php

declare(strict_types=1);

use Sift\Console\CommandRoute;
use Sift\Console\Commands\CommandHandler;
use Sift\Console\Commands\HelpCommand;
use Sift\Console\Commands\InitCommand;
use Sift\Console\Commands\RunToolCommandResult;
use Sift\Console\Commands\SkillsListCommand;
use Sift\Console\Commands\SkillsRemoveCommand;
use Sift\Console\Commands\ToolsListCommand;
use Sift\Console\Commands\ValidateCommand;
use Sift\Console\Commands\VersionCommand;
use Sift\Console\ConfirmationPrompt;
use Sift\Console\OutputFormat;
use Sift\Console\OutputPreferences;
use Sift\Console\OutputSize;
use Sift\Sift;
use Tests\Support\FixtureProject;

it('exposes built-in commands through the shared handler contract', function (): void {
    expect(new HelpCommand())->toBeInstanceOf(CommandHandler::class);
    expect(new VersionCommand())->toBeInstanceOf(CommandHandler::class);
    expect(new ToolsListCommand())->toBeInstanceOf(CommandHandler::class);
});

it('renders version command payload', function (): void {
    $payload = (new VersionCommand())->handle(new CommandRoute('version'), getcwd() ?: '.');

    expect($payload)->toMatchArray([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => [
            'version' => Sift::VERSION,
        ],
        'meta' => [
            'subcommand' => 'version',
        ],
    ]);
});

it('validates default config when no sift json exists', function (): void {
    $project = FixtureProject::create();
    $payload = (new ValidateCommand())->handle(new CommandRoute('validate'), $project->root());

    expect($payload)->toMatchArray([
        'tool' => 'sift',
        'status' => 'passed',
    ]);
    expect($payload['summary'])->toMatchArray([
        'config_path' => null,
        'using_defaults' => true,
    ]);
    expect($payload['meta'])->toBe([
        'subcommand' => 'validate',
        'warnings' => [],
    ]);
});

it('initializes config and reports already initialized projects', function (): void {
    $project = FixtureProject::create();
    $command = new InitCommand();
    $route = new CommandRoute('init');

    $created = $command->handle($route, $project->root());
    $configPath = $project->path('sift.json');

    expect($created['summary'])->toMatchArray([
        'config_path' => $configPath,
        'already_initialized' => false,
        'skill_installed' => false,
    ]);
    expect(is_file($configPath))->toBeTrue();

    $existing = $command->handle($route, $project->root());

    expect($existing['summary'])->toMatchArray([
        'config_path' => $configPath,
        'already_initialized' => true,
        'skill_installed' => false,
    ]);

    $forced = $command->handle(new CommandRoute('init', options: ['force' => true]), $project->root());

    expect($forced['summary'])->toMatchArray([
        'config_path' => $configPath,
        'already_initialized' => false,
        'skill_installed' => false,
    ]);
});

it('stores normalized and raw run tool command results', function (): void {
    $preferences = new OutputPreferences(OutputSize::Compact, false, false, false, OutputFormat::Terminal);
    $normalized = RunToolCommandResult::normalized(['tool' => 'pest'], $preferences);
    $raw = RunToolCommandResult::raw(2, $preferences);

    expect($normalized->payload())->toBe(['tool' => 'pest']);
    expect($normalized->exitCode())->toBe(0);
    expect($normalized->outputPreferences())->toBe($preferences);

    expect($raw->payload())->toBeNull();
    expect($raw->exitCode())->toBe(2);
    expect($raw->outputPreferences())->toBe($preferences);
});

it('lists no installed skills for an empty generic target', function (): void {
    $project = FixtureProject::create();
    $payload = (new SkillsListCommand())->handle(
        new CommandRoute('skills list', options: ['agent' => 'generic', 'skill' => ['sift,review']]),
        $project->root(),
    );

    expect($payload)->toMatchArray([
        'tool' => 'sift',
        'status' => 'passed',
        'summary' => ['total' => 0],
        'items' => [],
        'meta' => [
            'subcommand' => 'skills list',
            'targets' => ['generic'],
        ],
    ]);
});

it('reports missing skill removal for an empty generic target', function (): void {
    $project = FixtureProject::create();
    $payload = (new SkillsRemoveCommand())->handle(
        new CommandRoute('skills remove', arguments: ['sift'], options: ['yes' => true, 'agent' => 'generic']),
        $project->root(),
    );

    expect($payload['summary'])->toBe(['removed' => 0]);
    expect($payload['items'])->toBe([[
        'name' => 'sift',
        'target' => 'generic',
        'path' => $project->path('AGENTS.md'),
        'action' => 'missing',
    ]]);
    expect($payload['meta'])->toBe([
        'subcommand' => 'skills remove',
        'targets' => ['generic'],
        'skills' => ['sift'],
    ]);
});

it('allows interactive confirmation before removing skills', function (): void {
    $project = FixtureProject::create();
    $output = '';
    $prompt = new ConfirmationPrompt(
        interactive: static fn(): bool => true,
        reader: static fn(): string => "y\n",
        writer: static function (string $message) use (&$output): void {
            $output .= $message;
        },
    );

    $payload = (new SkillsRemoveCommand(confirmationPrompt: $prompt))->handle(
        new CommandRoute('skills remove', arguments: ['sift'], options: ['agent' => 'generic']),
        $project->root(),
    );

    expect($payload['summary'])->toBe(['removed' => 0]);
});
