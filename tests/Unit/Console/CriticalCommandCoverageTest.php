<?php

declare(strict_types=1);

use Sift\Console\CommandRoute;
use Sift\Console\Commands\RunToolCommand;
use Sift\Console\Commands\SkillsAddCommand;
use Sift\Console\Commands\SkillsUpdateCommand;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
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

function criticalCommandSkill(FixtureProject $source, string $body): void
{
    $source->write('SKILL.md', sprintf(
        "---\nname: critical-review\ndescription: Critical review.\n---\n\n# Critical Review\n\n%s\n",
        $body,
    ));
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
