<?php

declare(strict_types=1);

use Sift\Console\CommandRoute;
use Sift\Console\Commands\RunToolCommand;
use Sift\Console\Commands\SkillsAddCommand;
use Sift\Console\Commands\SkillsRemoveCommand;
use Sift\Console\Commands\SkillsUpdateCommand;
use Sift\Console\ConfirmationPrompt;
use Sift\Console\InteractivePrompt;
use Sift\Core\ErrorCode;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Registry\ToolRegistry;
use Sift\Tools\AbstractCliToolAdapter;
use Sift\Tools\ToolContext;
use Sift\Tools\ToolRunner;
use Tests\Support\FixtureProject;

it('runs add and update skill handlers directly', function (): void {
    $project = FixtureProject::create();
    $source = FixtureProject::create('sift-critical-skill-');
    criticalCommandSkill($source, 'Old guidance');

    $added = (new SkillsAddCommand())->handle(
        new CommandRoute('skills.add', [$source->root()], ['agent' => 'generic', 'yes' => true]),
        $project->root(),
    );

    criticalCommandSkill($source, 'Updated guidance');

    $updated = (new SkillsUpdateCommand())->handle(
        new CommandRoute('skills.update', ['critical-review'], ['agent' => 'generic', 'yes' => true]),
        $project->root(),
    );

    expect($added['summary'])->toMatchArray(['installed' => 1, 'skills' => 1, 'targets' => 1]);
    expect($updated['summary'])->toMatchArray(['updated' => 1, 'skills' => 1, 'targets' => 1]);
    expect((string) file_get_contents($project->path('AGENTS.md')))->toContain('Updated guidance');
});

it('updates installed skills when no update names are provided', function (): void {
    $project = FixtureProject::create();
    $source = FixtureProject::create('sift-critical-skill-');
    criticalCommandSkill($source, 'Old guidance');

    (new SkillsAddCommand())->handle(
        new CommandRoute('skills.add', [$source->root()], ['agent' => 'generic', 'yes' => true]),
        $project->root(),
    );

    criticalCommandSkill($source, 'Updated all guidance');

    $payload = (new SkillsUpdateCommand())->handle(
        new CommandRoute('skills.update', options: ['agent' => 'generic', 'yes' => true]),
        $project->root(),
    );

    expect($payload['summary'])->toMatchArray(['updated' => 1, 'skills' => 1, 'targets' => 1]);
    expect((string) file_get_contents($project->path('AGENTS.md')))->toContain('Updated all guidance');
});

it('prompts for the update scope in terminal skills mode', function (): void {
    $project = FixtureProject::create();
    $source = FixtureProject::create('sift-critical-skill-');
    criticalCommandSkill($source, 'Old interactive update guidance');

    (new SkillsAddCommand())->handle(
        new CommandRoute('skills.add', [$source->root()], ['agent' => 'generic', 'yes' => true]),
        $project->root(),
    );

    criticalCommandSkill($source, 'Updated interactive update guidance');

    $keys = ['enter'];
    $output = '';
    $payload = (new SkillsUpdateCommand(
        confirmationPrompt: new ConfirmationPrompt(
            interactive: static fn(): bool => true,
            reader: static fn(): string => "y\n",
            writer: static function (): void {},
        ),
        interactivePrompt: new InteractivePrompt(
            keyReader: static function () use (&$keys): string {
                return array_shift($keys) ?? 'escape';
            },
            writer: static function (string $message) use (&$output): void {
                $output .= $message;
            },
        ),
    ))->handle(
        new CommandRoute('skills.update', options: ['agent' => 'generic']),
        $project->root(),
    );

    expect(stripCriticalCommandAnsi($output))->toContain('Update scope');
    expect($payload['summary'])->toMatchArray(['updated' => 1, 'skills' => 1, 'targets' => 1]);
    expect((string) file_get_contents($project->path('AGENTS.md')))->toContain('Updated interactive update guidance');
});

it('previews skills add without requiring confirmation or writing targets', function (): void {
    $project = FixtureProject::create();
    $source = FixtureProject::create('sift-critical-skill-');
    criticalCommandSkill($source, 'Preview guidance');

    $payload = (new SkillsAddCommand())->handle(
        new CommandRoute(
            'skills.add',
            [$source->root()],
            ['list' => true, 'agent' => 'generic', 'yes' => true, 'all' => true],
        ),
        $project->root(),
    );
    $items = criticalCommandPayloadList($payload, 'items');
    $firstItem = criticalCommandPayloadMap($items[0] ?? null);

    expect($payload['summary'])->toMatchArray(['total' => 1]);
    expect($firstItem['name'] ?? null)->toBe('critical-review');
    expect($payload['meta'])->toMatchArray([
        'subcommand' => 'skills add --list',
        'ignored_options' => ['agent', 'yes', 'all'],
    ]);
    expect($project->path('AGENTS.md'))->not->toBeFile();
});

it(
    'runs skills add prompts in terminal skills mode without requiring yes',
    function (): void {
        $project = FixtureProject::create();
        $source = FixtureProject::create('sift-critical-skill-');
        criticalCommandSkill($source, 'Interactive guidance');
        $project->write('AGENTS.md', "Manual instructions\n");

        $keys = ['enter', 'enter', 'enter'];
        $payload = (new SkillsAddCommand(
            interactivePrompt: new InteractivePrompt(
                keyReader: static function () use (&$keys): string {
                    return array_shift($keys) ?? 'escape';
                },
                writer: static function (): void {},
            ),
        ))->handle(
            new CommandRoute('skills.add', [$source->root()]),
            $project->root(),
        );
        $items = criticalCommandPayloadList($payload, 'items');
        $firstItem = criticalCommandPayloadMap($items[0] ?? null);

        expect($payload['summary'])->toMatchArray(['installed' => 1, 'skills' => 1, 'targets' => 1]);
        expect($firstItem['target'] ?? null)->toBe('standard');
        expect((string) file_get_contents($project->path('.agents/skills/critical-review/SKILL.md')))->toContain('Interactive guidance');
    },
);

it('prompts for the installation scope after selecting interactive agents', function (): void {
    $project = FixtureProject::create();
    $source = FixtureProject::create('sift-critical-skill-');
    $home = FixtureProject::create('sift-critical-home-');
    criticalCommandSkill($source, 'Global interactive guidance');

    $previousHome = getenv('HOME');
    $previousUserProfile = getenv('USERPROFILE');
    putenv('HOME=' . $home->root());
    putenv('USERPROFILE=' . $home->root());

    try {
        $keys = ['enter', 'down', 'enter', 'enter'];
        $payload = (new SkillsAddCommand(
            interactivePrompt: new InteractivePrompt(
                keyReader: static function () use (&$keys): string {
                    return array_shift($keys) ?? 'escape';
                },
                writer: static function (): void {},
            ),
        ))->handle(
            new CommandRoute('skills.add', [$source->root()]),
            $project->root(),
        );
    } finally {
        putenv($previousHome === false ? 'HOME' : 'HOME=' . $previousHome);
        putenv($previousUserProfile === false ? 'USERPROFILE' : 'USERPROFILE=' . $previousUserProfile);
    }

    expect($payload['meta'])->toMatchArray(['global' => true, 'targets' => ['standard']]);
    expect($home->path('.config/agents/skills/critical-review/SKILL.md'))->toBeFile();
    expect($project->path('.agents/skills/critical-review/SKILL.md'))->not->toBeFile();
});

it('removes selected skills from the interactive skills remove flow', function (): void {
    $project = FixtureProject::create();
    $source = FixtureProject::create('sift-critical-skill-');
    criticalCommandSkill($source, 'Interactive removal guidance');

    (new SkillsAddCommand())->handle(
        new CommandRoute('skills.add', [$source->root()], ['agent' => 'generic', 'yes' => true]),
        $project->root(),
    );

    $keys = ['space', 'enter', 'char:y'];
    $payload = (new SkillsRemoveCommand(
        interactivePrompt: new InteractivePrompt(
            keyReader: static function () use (&$keys): string {
                return array_shift($keys) ?? 'escape';
            },
            writer: static function (): void {},
        ),
    ))->handle(
        new CommandRoute('skills.remove', options: ['agent' => 'generic']),
        $project->root(),
    );

    expect($payload['summary'])->toBe(['removed' => 1]);
    expect((string) file_get_contents($project->path('AGENTS.md')))->not->toContain('Interactive removal guidance');
});

it('limits interactive remove targets to the selected skill metadata', function (): void {
    $project = FixtureProject::create();
    $source = FixtureProject::create('sift-critical-skill-');
    criticalCommandSkill($source, 'Standard removal guidance');

    (new SkillsAddCommand())->handle(
        new CommandRoute('skills.add', [$source->root()], ['agent' => 'standard', 'yes' => true]),
        $project->root(),
    );

    $keys = ['enter', 'enter'];
    $output = '';
    $payload = (new SkillsRemoveCommand(
        interactivePrompt: new InteractivePrompt(
            keyReader: static function () use (&$keys): string {
                return array_shift($keys) ?? 'escape';
            },
            writer: static function (string $message) use (&$output): void {
                $output .= $message;
            },
        ),
    ))->handle(
        new CommandRoute('skills.remove'),
        $project->root(),
    );

    expect($payload['meta'])->toMatchArray(['targets' => ['standard'], 'skills' => ['critical-review']]);
    expect(stripCriticalCommandAnsi($output))->toContain('Remove skill(s) critical-review from target(s) standard? [Y/n]');
    expect(stripCriticalCommandAnsi($output))->not->toContain('aider-desk');
    expect($project->path('.agents/skills/critical-review'))->not->toBeDirectory();
});

it('runs a normalized tool handler directly with process reporting and history disabled', function (): void {
    $project = FixtureProject::create();
    $stderr = '';
    $runner = new ToolRunner(new ToolRegistry(criticalCommandAdapter()));
    $command = new RunToolCommand(
        toolRunner: $runner,
        stderrWriter: static function (string $contents) use (&$stderr): void {
            $stderr .= $contents;
        },
    );

    $result = $command->handle(
        new CommandRoute(
            'run.tool',
            ['critical-tool'],
            globalOptions: ['no-history' => true, 'show-process' => true],
        ),
        $project->root(),
    );

    expect($result->exitCode())->toBe(0);
    expect($result->payload())->toMatchArray(['tool' => 'critical-tool', 'status' => 'passed']);
    expect($stderr)->toContain('"type":"process"');
    expect($project->path('.sift/history'))->not->toBeDirectory();
});

it('adds limited raw snippets to debug parse failures', function (): void {
    $project = FixtureProject::create();
    $runner = new ToolRunner(new ToolRegistry(criticalCommandParseFailureAdapter()));
    $command = new RunToolCommand(toolRunner: $runner);

    try {
        $command->handle(
            new CommandRoute(
                'run.tool',
                ['critical-parse-failure'],
                globalOptions: ['no-history' => true, 'debug' => true],
            ),
            $project->root(),
        );
    } catch (UserFacingException $userFacingException) {
        $context = $userFacingException->context();

        expect($userFacingException->errorCode())->toBe(ErrorCode::ParseFailure);
        expect($context['stdout'] ?? null)->toBe(str_repeat('o', 4000));
        expect($context['stderr'] ?? null)->toBe(str_repeat('e', 4000));

        return;
    }

    throw new RuntimeException('Expected parse failure.');
});

function criticalCommandSkill(FixtureProject $source, string $body): void
{
    $source->write('SKILL.md', sprintf(
        "---\nname: critical-review\ndescription: Critical review.\n---\n\n# Critical Review\n\n%s\n",
        $body,
    ));
}

function stripCriticalCommandAnsi(string $output): string
{
    return preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $output) ?? $output;
}

/**
 * @param array<string, mixed> $payload
 *
 * @return list<mixed>
 */
function criticalCommandPayloadList(array $payload, string $key): array
{
    $value = $payload[$key] ?? null;

    if (! is_array($value) || ! array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected "%s" list payload value.', $key));
    }

    return $value;
}

/**
 * @return array<string, mixed>
 */
function criticalCommandPayloadMap(mixed $value): array
{
    if (! is_array($value) || array_is_list($value)) {
        throw new RuntimeException('Expected object payload item.');
    }

    $normalized = [];

    foreach ($value as $key => $item) {
        if (! is_string($key)) {
            throw new RuntimeException('Expected string payload item keys.');
        }

        $normalized[$key] = $item;
    }

    return $normalized;
}

function criticalCommandAdapter(): AbstractCliToolAdapter
{
    return new readonly class extends AbstractCliToolAdapter {
        protected function name(): string
        {
            return 'critical-tool';
        }

        protected function description(): string
        {
            return 'Critical coverage tool.';
        }

        protected function binaryCandidates(): array
        {
            return [PHP_BINARY];
        }

        protected function installHint(): string
        {
            return 'Install PHP.';
        }

        protected function defaultContext(): string
        {
            return 'test';
        }

        protected function defaultArguments(ToolContext $context): array
        {
            return ['-r', 'echo "{}";'];
        }

        public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
        {
            return NormalizedResult::passed($context->toolName());
        }
    };
}

function criticalCommandParseFailureAdapter(): AbstractCliToolAdapter
{
    return new readonly class extends AbstractCliToolAdapter {
        protected function name(): string
        {
            return 'critical-parse-failure';
        }

        protected function description(): string
        {
            return 'Critical parse failure tool.';
        }

        protected function binaryCandidates(): array
        {
            return [PHP_BINARY];
        }

        protected function installHint(): string
        {
            return 'Install PHP.';
        }

        protected function defaultContext(): string
        {
            return 'test';
        }

        protected function defaultArguments(ToolContext $context): array
        {
            return [
                '-r',
                sprintf(
                    'fwrite(STDOUT, %s); fwrite(STDERR, %s);',
                    var_export(str_repeat('o', 4100), true),
                    var_export(str_repeat('e', 4100), true),
                ),
            ];
        }

        public function parse(ExecutionResult $execution, ToolContext $context, PreparedCommand $command): NormalizedResult
        {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::ParseFailure,
                message: 'Could not parse critical output.',
                context: ['reason' => 'invalid shape'],
            );
        }
    };
}
