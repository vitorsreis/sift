<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Console\ConfirmationPrompt;
use Sift\Console\InteractivePrompt;
use Sift\Console\InvalidUsageException;
use Sift\Skills\ClonedSkillSource;
use Sift\Skills\Skill;
use Sift\Skills\SkillDiscovery;
use Sift\Skills\SkillRepositoryCloner;
use Sift\Skills\SkillSelector;
use Sift\Skills\SkillSource;
use Sift\Skills\SkillSourceResolver;
use Sift\Skills\SkillTargetLock;
use Sift\Skills\Targets\InstructionTargetRegistry;
use Sift\Skills\Targets\SkillTargetInstaller;
use Sift\Skills\Targets\SkillTargetInstallResult;

final readonly class SkillsAddCommand implements CommandHandler
{
    public function __construct(
        private SkillSourceResolver $sourceResolver = new SkillSourceResolver(),
        private SkillRepositoryCloner $repositoryCloner = new SkillRepositoryCloner(),
        private SkillDiscovery $discovery = new SkillDiscovery(),
        private SkillSelector $selector = new SkillSelector(),
        private InstructionTargetRegistry $targetRegistry = new InstructionTargetRegistry(),
        private SkillTargetInstaller $targetInstaller = new SkillTargetInstaller(),
        private SkillTargetLock $targetLock = new SkillTargetLock(),
        private ConfirmationPrompt $confirmationPrompt = new ConfirmationPrompt(),
        private InteractivePrompt $interactivePrompt = new InteractivePrompt(),
    ) {}

    public function handle(CommandRoute $route, string $cwd): array
    {
        $sourceArgument = $route->arguments()[0] ?? null;

        if (! is_string($sourceArgument) || trim($sourceArgument) === '') {
            throw new InvalidUsageException('skills add requires a source.');
        }

        if (! $this->optionBool($route, 'list') && ! $this->optionBool($route, 'yes') && ! $this->optionBool($route, 'all') && $this->jsonRequested($route)) {
            $this->confirmationPrompt->assertInteractive();
        }

        $source = $this->sourceResolver->resolve($sourceArgument, $cwd);
        $checkout = $this->checkout($source, $cwd);

        try {
            $resolvedSource = $checkout->source();
            $sourcePath = $resolvedSource->path();

            if ($sourcePath === null) {
                throw new InvalidUsageException(sprintf('Skill source "%s" does not have a local path.', $resolvedSource->source()));
            }

            $skills = $this->discovery->discover($sourcePath, $resolvedSource->source(), $resolvedSource->type());

            if ($skills === []) {
                throw new InvalidUsageException(sprintf('Skill source "%s" does not contain any SKILL.md files.', $resolvedSource->source()));
            }

            if ($this->optionBool($route, 'list')) {
                return $this->previewPayload($route, $resolvedSource, $skills);
            }

            $selectedSkills = $this->selectedSkills($route, $resolvedSource, $skills);
            $targets = $this->targets($route, $cwd, $this->optionBool($route, 'global'));
            $global = $this->installGlobally($route, $targets);
            $this->assertConfirmed(
                $route,
                sprintf(
                    'Install skill(s) %s into target(s) %s?',
                    implode(', ', array_map(static fn(Skill $skill): string => $skill->name(), $selectedSkills)),
                    implode(', ', $targets),
                ),
            );
            $results = $this->targetLock->synchronized(
                $cwd,
                $targets,
                fn(): array => $this->targetInstaller->install($cwd, $selectedSkills, $targets, $resolvedSource, $global),
            );

            return $this->installPayload($route, $resolvedSource, $selectedSkills, $targets, $results, $global);
        } finally {
            $checkout->cleanup();
        }
    }

    private function checkout(SkillSource $source, string $cwd): ClonedSkillSource
    {
        if ($source->type() === 'github') {
            return $this->repositoryCloner->clone($source, $cwd);
        }

        return new ClonedSkillSource($source, static function (): void {});
    }

    /**
     * @param list<Skill> $skills
     *
     * @return array<string, mixed>
     */
    private function previewPayload(CommandRoute $route, SkillSource $source, array $skills): array
    {
        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'total' => count($skills),
            ],
            'items' => array_map($this->item(...), $skills),
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'skills add --list',
                'source' => $source->source(),
                'source_type' => $source->type(),
                'resolved_ref' => $source->resolvedRef(),
                'warnings' => $source->warnings(),
                'global' => $this->optionBool($route, 'global'),
                'ignored_options' => $this->ignoredOptions($route),
            ],
        ];
    }

    /**
     * @param list<Skill> $skills
     * @param list<string> $targets
     * @param list<SkillTargetInstallResult> $results
     *
     * @return array<string, mixed>
     */
    private function installPayload(CommandRoute $route, SkillSource $source, array $skills, array $targets, array $results, bool $global): array
    {
        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'installed' => count($results),
                'skills' => count($skills),
                'targets' => count($targets),
            ],
            'items' => array_map(static fn(SkillTargetInstallResult $result): array => $result->toItem(), $results),
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'skills add',
                'source' => $source->source(),
                'source_type' => $source->type(),
                'resolved_ref' => $source->resolvedRef(),
                'warnings' => $source->warnings(),
                'targets' => $targets,
                'global' => $global,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(Skill $skill): array
    {
        return [
            'name' => $skill->name(),
            'description' => $skill->description(),
            'source' => $skill->source(),
            'source_type' => $skill->sourceType(),
            'path' => $skill->path(),
        ];
    }

    /**
     * @return list<string>
     */
    private function ignoredOptions(CommandRoute $route): array
    {
        $ignored = [];

        foreach (['agent', 'yes', 'all'] as $option) {
            if (array_key_exists($option, $route->options())) {
                $ignored[] = $option;
            }
        }

        return $ignored;
    }

    private function optionBool(CommandRoute $route, string $name): bool
    {
        return ($route->options()[$name] ?? false) === true;
    }

    private function optionProvided(CommandRoute $route, string $name): bool
    {
        return array_key_exists($name, $route->options());
    }

    private function jsonRequested(CommandRoute $route): bool
    {
        return ($route->globalOptions()['json'] ?? false) === true || ($route->options()['json'] ?? false) === true;
    }

    private function colorEnabled(CommandRoute $route): bool
    {
        return ($route->globalOptions()['no-color'] ?? false) !== true
            && ($route->options()['no-color'] ?? false) !== true;
    }

    /**
     * @param list<Skill> $skills
     *
     * @return list<Skill>
     */
    private function selectedSkills(CommandRoute $route, SkillSource $source, array $skills): array
    {
        $selector = $this->skillSelector($route, $source);

        if ($selector !== null || count($skills) === 1 || $this->optionBool($route, 'yes')) {
            return $this->selector->select($skills, $selector, $source->source());
        }

        $selected = $this->interactivePrompt->multiselect(
            'Select skills to install',
            array_map(
                static fn(Skill $skill): array => [
                    'value' => $skill->name(),
                    'label' => $skill->name(),
                    'hint' => $skill->description(),
                ],
                $skills,
            ),
            $this->colorEnabled($route),
        );

        if ($selected === null) {
            throw new InvalidUsageException('Skill command cancelled.');
        }

        return $this->selector->select($skills, implode(',', $selected), $source->source());
    }

    private function assertConfirmed(CommandRoute $route, string $message): void
    {
        if ($this->optionBool($route, 'yes') || $this->optionBool($route, 'all')) {
            return;
        }

        if (! $this->interactivePrompt->confirm($message, $this->colorEnabled($route))) {
            throw new InvalidUsageException('Skill command cancelled.');
        }
    }

    private function skillSelector(CommandRoute $route, SkillSource $source): ?string
    {
        if ($this->optionBool($route, 'all')) {
            return '*';
        }

        $selectors = $this->stringOptionValues($route, 'skill');

        if ($selectors !== []) {
            return implode(',', $selectors);
        }

        return $source->requestedSkill();
    }

    /**
     * @return list<string>
     */
    private function targets(CommandRoute $route, string $cwd, bool $global): array
    {
        if ($this->optionBool($route, 'all')) {
            return $this->targetRegistry->writeCapableNames($global);
        }

        $agent = $route->options()['agent'] ?? null;

        if (! is_string($agent) || trim($agent) === '') {
            if ($this->optionBool($route, 'yes')) {
                throw new InvalidUsageException('skills add requires --agent or --all when installing.');
            }

            $selected = $this->interactivePrompt->multiselect(
                'Which agents do you want to install to?',
                $this->targetRegistry->agentChoices($cwd, $global),
                $this->colorEnabled($route),
            );

            if ($selected === null) {
                throw new InvalidUsageException('Skill command cancelled.');
            }

            return $selected;
        }

        $targets = [];

        foreach (explode(',', $agent) as $target) {
            $target = trim($target);

            if ($target === '' || in_array($target, ['*', 'all'], true)) {
                return $this->targetRegistry->writeCapableNames($global);
            }

            $targets[] = $target;
        }

        return array_values(array_unique($targets));
    }

    /**
     * @param list<string> $targets
     */
    private function installGlobally(CommandRoute $route, array $targets): bool
    {
        if ($this->optionProvided($route, 'global')) {
            return $this->optionBool($route, 'global');
        }

        if ($this->optionBool($route, 'yes') || $this->optionBool($route, 'all')) {
            return false;
        }

        if (! $this->targetRegistry->anySupportsGlobal($targets)) {
            return false;
        }

        $scope = $this->interactivePrompt->select(
            'Installation scope',
            [
                [
                    'value' => 'project',
                    'label' => 'Project',
                    'hint' => 'Install in current directory.',
                ],
                [
                    'value' => 'global',
                    'label' => 'Global',
                    'hint' => 'Install in the user-level directory.',
                ],
            ],
            $this->colorEnabled($route),
        );

        if ($scope === null) {
            throw new InvalidUsageException('Skill command cancelled.');
        }

        return $scope === 'global';
    }

    /**
     * @return list<string>
     */
    private function stringOptionValues(CommandRoute $route, string $name): array
    {
        $value = $route->options()[$name] ?? null;
        $values = is_array($value) ? $value : [$value];
        $strings = [];

        foreach ($values as $item) {
            if (is_string($item) && trim($item) !== '') {
                $strings[] = trim($item);
            }
        }

        return $strings;
    }
}
