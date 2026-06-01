<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;
use Sift\Skills\ClonedSkillSource;
use Sift\Skills\Skill;
use Sift\Skills\SkillDiscovery;
use Sift\Skills\SkillRepositoryCloner;
use Sift\Skills\SkillSource;
use Sift\Skills\SkillSourceResolver;

final readonly class SkillsAddCommand implements CommandHandler
{
    public function __construct(
        private SkillSourceResolver $sourceResolver = new SkillSourceResolver(),
        private SkillRepositoryCloner $repositoryCloner = new SkillRepositoryCloner(),
        private SkillDiscovery $discovery = new SkillDiscovery(),
    ) {}

    public function handle(CommandRoute $route, string $cwd): array
    {
        if (! $this->optionBool($route, 'list')) {
            throw new InvalidUsageException('Skill installation is not implemented yet. Use --list to preview skills.');
        }

        $sourceArgument = $route->arguments()[0] ?? null;

        if (! is_string($sourceArgument) || trim($sourceArgument) === '') {
            throw new InvalidUsageException('skills add requires a source.');
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

            return $this->payload($route, $resolvedSource, $skills);
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
    private function payload(CommandRoute $route, SkillSource $source, array $skills): array
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
}
