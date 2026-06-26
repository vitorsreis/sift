<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Config\ConfigLoader;
use Sift\Config\ConfigWriter;
use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;
use Sift\Filesystem\Path;
use Sift\Skills\SkillDiscovery;
use Sift\Skills\SkillSelector;
use Sift\Skills\SkillSourceResolver;
use Sift\Skills\SkillTargetLock;
use Sift\Skills\Targets\SkillTargetInstaller;
use Sift\Skills\Targets\SkillTargetInstallResult;
use Sift\Workspace\WorkspaceResolver;

final readonly class InitCommand implements CommandHandler
{
    public function __construct(
        private ConfigLoader $configLoader = new ConfigLoader(),
        private ConfigWriter $configWriter = new ConfigWriter(),
        private WorkspaceResolver $workspaceResolver = new WorkspaceResolver(),
        private SkillSourceResolver $skillSourceResolver = new SkillSourceResolver(),
        private SkillDiscovery $skillDiscovery = new SkillDiscovery(),
        private SkillSelector $skillSelector = new SkillSelector(),
        private SkillTargetInstaller $skillTargetInstaller = new SkillTargetInstaller(),
        private SkillTargetLock $skillTargetLock = new SkillTargetLock(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CommandRoute $route, string $cwd): array
    {
        if ($this->optionBool($route, 'skill') && $this->optionBool($route, 'no-skill')) {
            throw new InvalidUsageException('Options "--skill" and "--no-skill" cannot be used together.');
        }

        $workspace = $this->workspaceResolver->resolve($cwd, $this->configPath($route));
        $configPath = $workspace->configPath() ?? Path::join($workspace->projectRoot(), 'sift.json');
        $force = $this->optionBool($route, 'force');
        $alreadyInitialized = false;
        $existing = null;

        if (is_file($configPath)) {
            $validatedWorkspace = $this->workspaceResolver->resolve($cwd, $configPath);
            $this->configLoader->load($validatedWorkspace);

            if (! $force) {
                $alreadyInitialized = true;
            } else {
                $existing = $this->configLoader->readDocument($configPath);
            }
        }

        if (! $alreadyInitialized) {
            $this->configWriter->writeDefaults($configPath, $existing);
        }

        $skillResults = $this->installBundledSkill($route, $cwd);

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'config_path' => $configPath,
                'already_initialized' => $alreadyInitialized,
                'skill_installed' => $skillResults !== [],
            ],
            'items' => array_map(static fn(SkillTargetInstallResult $result): array => $result->toItem(), $skillResults),
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'init',
            ],
        ];
    }

    private function configPath(CommandRoute $route): ?string
    {
        $options = $route->options();
        $globalOptions = $route->globalOptions();
        $config = $options['config'] ?? $globalOptions['config'] ?? null;

        return is_string($config) ? $config : null;
    }

    private function optionBool(CommandRoute $route, string $name): bool
    {
        $value = $route->options()[$name] ?? false;

        return $value === true;
    }

    /**
     * @return list<SkillTargetInstallResult>
     */
    private function installBundledSkill(CommandRoute $route, string $cwd): array
    {
        if ($this->optionBool($route, 'no-skill')) {
            return [];
        }

        if (! $this->optionBool($route, 'yes') && ! $this->optionBool($route, 'skill')) {
            return [];
        }

        $source = $this->skillSourceResolver->resolve('sift', $cwd);
        $sourcePath = $source->path();

        if ($sourcePath === null) {
            throw new InvalidUsageException('Bundled skill source does not have a local path.');
        }

        $skills = $this->skillDiscovery->discover($sourcePath, $source->source(), $source->type());
        $selectedSkills = $this->skillSelector->select($skills, 'sift', $source->source());

        return $this->skillTargetLock->synchronized(
            $cwd,
            ['standard'],
            fn(): array => $this->skillTargetInstaller->install($cwd, $selectedSkills, ['standard'], $source),
        );
    }
}
