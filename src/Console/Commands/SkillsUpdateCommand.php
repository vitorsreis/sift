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
use Sift\Skills\SkillManagedMetadata;
use Sift\Skills\SkillRepositoryCloner;
use Sift\Skills\SkillSelector;
use Sift\Skills\SkillService;
use Sift\Skills\SkillSource;
use Sift\Skills\SkillSourceResolver;
use Sift\Skills\SkillTargetLock;
use Sift\Skills\Targets\InstructionTargetRegistry;
use Sift\Skills\Targets\SkillTargetInstaller;
use Sift\Skills\Targets\SkillTargetInstallResult;

final readonly class SkillsUpdateCommand implements CommandHandler
{
    public function __construct(
        private SkillService $skillService = new SkillService(),
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
        $scope = $this->updateScope($route, $cwd);
        $scopeGlobals = $this->scopeGlobals($scope);
        $scopePlans = [];
        $allTargets = [];

        foreach ($scopeGlobals as $global) {
            $targets = $this->targets($route, $global, $scope === 'both');
            $scopePlans[] = [
                'global' => $global,
                'targets' => $targets,
            ];
            $allTargets = [...$allTargets, ...$targets];
        }

        $allTargets = array_values(array_unique($allTargets));
        $names = $this->selectedNames($route);
        $this->assertConfirmed(
            $route,
            sprintf(
                'Update managed skill(s) %s for target(s) %s in %s scope?',
                $names === [] ? 'all' : implode(', ', $names),
                implode(', ', $allTargets),
                $scope,
            ),
        );
        $installed = [];
        $results = [];

        foreach ($scopePlans as $scopePlan) {
            $global = $scopePlan['global'];
            $targets = $scopePlan['targets'];

            if ($targets === []) {
                continue;
            }

            $scopeResults = $this->targetLock->synchronized($cwd, $targets, function () use ($cwd, $route, $targets, $global, &$installed): array {
                $scopeInstalled = $this->selectedMetadata($this->skillService->inventory($cwd, $targets, $global), $route);
                $scopeResults = [];

                foreach ($scopeInstalled as $metadata) {
                    foreach ($this->update($cwd, $metadata, $targets, $global) as $result) {
                        $scopeResults[] = $result;
                    }
                }

                foreach ($scopeInstalled as $metadata) {
                    $installed[] = $metadata;
                }

                return $scopeResults;
            });

            foreach ($scopeResults as $result) {
                $results[] = $result;
            }
        }

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'updated' => count($results),
                'skills' => count($installed),
                'targets' => count($allTargets),
            ],
            'items' => array_map(static fn(SkillTargetInstallResult $result): array => $result->toItem(), $results),
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'skills update',
                'targets' => $allTargets,
                'global' => $scope === 'global',
                'scope' => $scope,
            ],
        ];
    }

    private function updateScope(CommandRoute $route, string $cwd): string
    {
        $names = $this->selectedNames($route);

        if ($names !== []) {
            if ($this->optionBool($route, 'global')) {
                return 'global';
            }

            if ($this->optionBool($route, 'project')) {
                return 'project';
            }

            return 'both';
        }

        if ($this->optionBool($route, 'global') && $this->optionBool($route, 'project')) {
            return 'both';
        }

        if ($this->optionBool($route, 'global')) {
            return 'global';
        }

        if ($this->optionBool($route, 'project')) {
            return 'project';
        }

        if ($this->optionBool($route, 'yes') || $this->optionBool($route, 'all')) {
            return $this->hasProjectSkills($route, $cwd) ? 'project' : 'global';
        }

        $scope = $this->interactivePrompt->select(
            'Update scope',
            [
                [
                    'value' => 'project',
                    'label' => 'Project',
                    'hint' => 'Update skills in current directory',
                ],
                [
                    'value' => 'global',
                    'label' => 'Global',
                    'hint' => 'Update skills in home directory',
                ],
                [
                    'value' => 'both',
                    'label' => 'Both',
                    'hint' => 'Update all skills',
                ],
            ],
            $this->colorEnabled($route),
        );

        if ($scope === null) {
            throw new InvalidUsageException('Skill command cancelled.');
        }

        return $scope;
    }

    /**
     * @return list<bool>
     */
    private function scopeGlobals(string $scope): array
    {
        return match ($scope) {
            'project' => [false],
            'global' => [true],
            'both' => [false, true],
            default => throw new InvalidUsageException(sprintf('Unsupported update scope "%s".', $scope)),
        };
    }

    private function hasProjectSkills(CommandRoute $route, string $cwd): bool
    {
        return $this->skillService->inventory($cwd, $this->targets($route, false), false) !== [];
    }

    /**
     * @param list<SkillManagedMetadata> $items
     *
     * @return list<SkillManagedMetadata>
     */
    private function selectedMetadata(array $items, CommandRoute $route): array
    {
        $names = $this->selectedNames($route);

        if ($names === []) {
            return $items;
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
    private function update(string $cwd, SkillManagedMetadata $metadata, array $requestedTargets, bool $global): array
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

            return $this->targetInstaller->install($cwd, $this->assertOneSkill($selectedSkills, $metadata), $targets, $resolvedSource, $global);
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
    private function targets(CommandRoute $route, bool $global, bool $skipUnsupportedGlobal = false): array
    {
        if ($this->optionBool($route, 'all')) {
            return $this->targetRegistry->writeCapableNames($global);
        }

        $agents = $this->stringOptionValues($route, 'agent');

        if ($agents === []) {
            return $this->targetRegistry->writeCapableNames($global);
        }

        $targets = [];

        foreach ($agents as $target) {
            $target = trim($target);

            if ($target === '' || in_array($target, ['*', 'all'], true)) {
                return $this->targetRegistry->writeCapableNames($global);
            }

            if ($global && $skipUnsupportedGlobal && ! $this->targetRegistry->supportsGlobal($target)) {
                continue;
            }

            $targets[] = $target;
        }

        return array_values(array_unique($targets));
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
            if (! is_string($item)) {
                continue;
            }

            foreach (explode(',', $item) as $string) {
                if (trim($string) !== '') {
                    $strings[] = trim($string);
                }
            }
        }

        return $strings;
    }

    private function assertConfirmed(CommandRoute $route, string $message): void
    {
        if ($this->optionBool($route, 'yes') || $this->optionBool($route, 'all')) {
            return;
        }

        $this->confirmationPrompt->confirm($message, $this->colorEnabled($route));
    }

    private function optionBool(CommandRoute $route, string $name): bool
    {
        return ($route->options()[$name] ?? false) === true;
    }

    private function colorEnabled(CommandRoute $route): bool
    {
        return ($route->globalOptions()['no-color'] ?? false) !== true
            && ($route->options()['no-color'] ?? false) !== true;
    }
}
