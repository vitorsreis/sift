<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
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
    ) {}

    public function handle(CommandRoute $route, string $cwd): array
    {
        $sourceArgument = $route->arguments()[0] ?? null;

        if (! is_string($sourceArgument) || trim($sourceArgument) === '') {
            throw new InvalidUsageException('skills add requires a source.');
        }

        if (! $this->optionBool($route, 'list')) {
            $this->assertConfirmed($route);
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

            $selectedSkills = $this->selector->select($skills, $this->skillSelector($route), $resolvedSource->source());
            $targets = $this->targets($route);
            $results = $this->targetLock->synchronized(
                $cwd,
                $targets,
                fn(): array => $this->targetInstaller->install($cwd, $selectedSkills, $targets, $resolvedSource),
            );

            return $this->installPayload($route, $resolvedSource, $selectedSkills, $targets, $results);
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
    private function installPayload(CommandRoute $route, SkillSource $source, array $skills, array $targets, array $results): array
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
                'global' => $this->optionBool($route, 'global'),
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

    private function assertConfirmed(CommandRoute $route): void
    {
        if ($this->optionBool($route, 'yes') || $this->optionBool($route, 'all')) {
            return;
        }

        throw new InvalidUsageException('Mutating skill commands require --yes or --all in non-interactive mode.');
    }

    private function skillSelector(CommandRoute $route): ?string
    {
        if ($this->optionBool($route, 'all')) {
            return '*';
        }

        $selector = $route->options()['skill'] ?? null;

        return is_string($selector) ? $selector : null;
    }

    /**
     * @return list<string>
     */
    private function targets(CommandRoute $route): array
    {
        if ($this->optionBool($route, 'all')) {
            return $this->targetRegistry->writeCapableNames();
        }

        $agent = $route->options()['agent'] ?? null;

        if (! is_string($agent) || trim($agent) === '') {
            throw new InvalidUsageException('skills add requires --agent or --all when installing.');
        }

        $targets = [];

        foreach (explode(',', $agent) as $target) {
            $target = trim($target);

            if ($target === '' || in_array($target, ['*', 'all'], true)) {
                return $this->targetRegistry->writeCapableNames();
            }

            $targets[] = $target;
        }

        return array_values(array_unique($targets));
    }
}
