<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Skills\Targets\InstructionTargetRegistry;
use Tests\Support\FixtureProject;

it('resolves agent skill targets and aliases', function (): void {
    $registry = new InstructionTargetRegistry();

    expect($registry->resolve('codex')->name())->toBe('codex');
    expect($registry->resolve('standard')->name())->toBe('standard');
    expect($registry->resolve('cursor')->name())->toBe('cursor');
    expect($registry->resolve('windsurf')->name())->toBe('windsurf');
    expect($registry->resolve('gemini')->name())->toBe('gemini-cli');
    expect($registry->resolve('gemini-cli')->name())->toBe('gemini-cli');
    expect($registry->resolve('generic')->name())->toBe('generic');
    expect($registry->resolve('claude')->name())->toBe('claude-code');
    expect($registry->resolve('copilot')->name())->toBe('github-copilot');
    expect($registry->resolve('vscode')->name())->toBe('github-copilot');
    expect($registry->resolve('vs-code')->name())->toBe('github-copilot');
    expect($registry->resolve('visual-studio-code')->name())->toBe('github-copilot');
    expect($registry->resolve('opencode')->name())->toBe('opencode');
    expect($registry->resolve('antigravity')->name())->toBe('antigravity');
    expect($registry->writeCapableNames())
        ->toContain('opencode')
        ->toContain('antigravity')
        ->toContain('openclaw')
        ->toContain('promptscript');

    try {
        $registry->resolve('unknown-agent');
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::UnsupportedTarget);
        expect($userFacingException->context()['recognized'] ?? null)->toBeFalse();

        return;
    }

    throw new RuntimeException('Expected unsupported target exception.');
});

it('marks existing project skill directories as selected agent choices', function (): void {
    $project = FixtureProject::create();
    mkdir($project->path('.windsurf/skills'), 0777, true);

    $choices = (new InstructionTargetRegistry())->agentChoices($project->root());
    $selected = array_values(array_filter(
        $choices,
        static fn(array $choice): bool => $choice['selected'] === true,
    ));

    expect(array_column($selected, 'value'))->toBe(['windsurf']);
    expect($selected[0]['hint'])->toBe('.windsurf/skills');
});

it('groups project agent choices that use the same skills directory', function (): void {
    $project = FixtureProject::create();
    $project->write('AGENTS.md', "# Project instructions\n");

    $choices = (new InstructionTargetRegistry())->agentChoices($project->root());
    $sharedChoices = array_values(array_filter(
        $choices,
        static fn(array $choice): bool => str_starts_with((string) $choice['hint'], '.agents/skills'),
    ));

    expect($sharedChoices)->toHaveCount(1);
    expect($sharedChoices[0]['value'])->toBe('standard');
    expect($sharedChoices[0]['label'])->toBe('standard');
    expect($sharedChoices[0]['hint'])->toBe('.agents/skills (Cursor, Gemini CLI, GitHub Copilot, OpenCode, Antigravity, Amp, Replit, +12 more)');
    expect($sharedChoices[0]['selected'])->toBeTrue();
    expect($choices[0])->toBe($sharedChoices[0]);
    expect($choices[1])->toMatchArray([
        'value' => 'generic',
        'label' => 'generic',
        'hint' => 'AGENTS.md',
        'selected' => false,
    ]);
});
