<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;
use Sift\Skills\ClonedSkillSource;
use Sift\Skills\Skill;
use Sift\Skills\SkillDiscovery;
use Sift\Skills\SkillInventory;
use Sift\Skills\SkillManagedMetadata;
use Sift\Skills\SkillRepositoryCloner;
use Sift\Skills\SkillSelector;
use Sift\Skills\SkillSource;
use Sift\Skills\SkillSourceResolver;
use Sift\Skills\SkillTargetLock;
use Sift\Skills\Targets\InstructionTargetRegistry;
use Sift\Skills\Targets\SkillTargetInstaller;
use Sift\Skills\Targets\SkillTargetInstallResult;

final readonly class SkillsUpdateCommand implements CommandHandler
{
    public function __construct(
        private SkillInventory $inventory = new SkillInventory(),
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
        $this->assertConfirmed($route);

        $targets = $this->targets($route);
        $installed = [];
        $results = $this->targetLock->synchronized($cwd, $targets, function () use ($cwd, $route, $targets, &$installed): array {
            $installed = $this->selectedMetadata($this->inventory->list($cwd, $targets), $route);
            $results = [];

            foreach ($installed as $metadata) {
                foreach ($this->update($cwd, $metadata, $targets) as $result) {
                    $results[] = $result;
                }
            }

            return $results;
        });

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'updated' => count($results),
                'skills' => count($installed),
                'targets' => count($targets),
            ],
            'items' => array_map(static fn(SkillTargetInstallResult $result): array => $result->toItem(), $results),
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'skills update',
                'targets' => $targets,
            ],
        ];
    }

    /**
     * @param list<SkillManagedMetadata> $items
     *
     * @return list<SkillManagedMetadata>
     */
    private function selectedMetadata(array $items, CommandRoute $route): array
    {
        $names = $this->selectedNames($route);

        if ($names === [] && $this->optionBool($route, 'all')) {
            return $items;
        }

        if ($names === []) {
            throw new InvalidUsageException('skills update requires a skill name or --all.');
        }

        $selected = array_values(array_filter(
            $items,
            static fn(SkillManagedMetadata $metadata): bool => in_array($metadata->name(), $names, true),
        ));

        if (count($selected) !== count($names)) {
            $available = array_map(static fn(SkillManagedMetadata $metadata): string => $metadata->name(), $items);
            $missing = array_values(array_diff($names, $available));

            throw new InvalidUsageException(sprintf('Managed skill "%s" was not found.', $missing[0] ?? $names[0]));
        }

        return $selected;
    }

    /**
     * @return list<string>
     */
    private function selectedNames(CommandRoute $route): array
    {
        $names = [];

        foreach ($route->arguments() as $argument) {
            if (trim($argument) !== '') {
                $names[] = trim($argument);
            }
        }

        $skillOption = $route->options()['skill'] ?? null;
        $skillOptions = is_array($skillOption) ? $skillOption : [$skillOption];

        foreach ($skillOptions as $skillOptionValue) {
            if (! is_string($skillOptionValue)) {
                continue;
            }

            foreach (explode(',', $skillOptionValue) as $name) {
                if (trim($name) !== '') {
                    $names[] = trim($name);
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param list<string> $requestedTargets
     *
     * @return list<SkillTargetInstallResult>
     */
    private function update(string $cwd, SkillManagedMetadata $metadata, array $requestedTargets): array
    {
        $source = $this->sourceResolver->resolve($metadata->source(), $cwd);
        $checkout = $this->checkout($source, $cwd);

        try {
            $resolvedSource = $checkout->source();
            $sourcePath = $resolvedSource->path();

            if ($sourcePath === null) {
                throw new InvalidUsageException(sprintf('Skill source "%s" does not have a local path.', $resolvedSource->source()));
            }

            $skills = $this->discovery->discover($sourcePath, $resolvedSource->source(), $resolvedSource->type());
            $selectedSkills = $this->selector->select($skills, $metadata->name(), $resolvedSource->source());
            $targets = array_values(array_intersect($metadata->targets(), $requestedTargets));

            return $this->targetInstaller->install($cwd, $this->assertOneSkill($selectedSkills, $metadata), $targets, $resolvedSource);
        } finally {
            $checkout->cleanup();
        }
    }

    /**
     * @param list<Skill> $skills
     *
     * @return list<Skill>
     */
    private function assertOneSkill(array $skills, SkillManagedMetadata $metadata): array
    {
        if (count($skills) !== 1) {
            throw new InvalidUsageException(sprintf('Skill "%s" was not found in its managed source.', $metadata->name()));
        }

        return $skills;
    }

    private function checkout(SkillSource $source, string $cwd): ClonedSkillSource
    {
        if ($source->type() === 'github') {
            return $this->repositoryCloner->clone($source, $cwd);
        }

        return new ClonedSkillSource($source, static function (): void {});
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

        if (! is_string($agent) || trim($agent) === '' || in_array(trim($agent), ['*', 'all'], true)) {
            return $this->targetRegistry->writeCapableNames();
        }

        $targets = [];

        foreach (explode(',', $agent) as $target) {
            $target = trim($target);

            if ($target !== '') {
                $targets[] = $target;
            }
        }

        return array_values(array_unique($targets));
    }

    private function assertConfirmed(CommandRoute $route): void
    {
        if ($this->optionBool($route, 'yes') || $this->optionBool($route, 'all')) {
            return;
        }

        throw new InvalidUsageException('Mutating skill commands require --yes or --all in non-interactive mode.');
    }

    private function optionBool(CommandRoute $route, string $name): bool
    {
        return ($route->options()[$name] ?? false) === true;
    }
}
