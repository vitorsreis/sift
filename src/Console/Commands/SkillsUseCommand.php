<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\Path;
use Sift\Filesystem\PathGuard;
use Sift\Skills\ClonedSkillSource;
use Sift\Skills\Skill;
use Sift\Skills\SkillDiscovery;
use Sift\Skills\SkillRepositoryCloner;
use Sift\Skills\SkillSelector;
use Sift\Skills\SkillSource;
use Sift\Skills\SkillSourceResolver;
use SplFileInfo;

final readonly class SkillsUseCommand implements CommandHandler
{
    public function __construct(
        private SkillSourceResolver $sourceResolver = new SkillSourceResolver(),
        private SkillRepositoryCloner $repositoryCloner = new SkillRepositoryCloner(),
        private SkillDiscovery $discovery = new SkillDiscovery(),
        private SkillSelector $selector = new SkillSelector(),
    ) {}

    public function handle(CommandRoute $route, string $cwd): array
    {
        $sourceArgument = $route->arguments()[0] ?? null;

        if (! is_string($sourceArgument) || trim($sourceArgument) === '') {
            throw new InvalidUsageException('skills use requires a source.');
        }

        if ($this->stringOptionValues($route, 'agent') !== []) {
            throw new InvalidUsageException('skills use --agent is not supported by Sift yet. Pipe the generated prompt into the agent instead.');
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
            $selectedSkills = $this->selector->select($skills, $this->skillSelector($route, $resolvedSource), $resolvedSource->source());

            if (count($selectedSkills) !== 1) {
                throw new InvalidUsageException('skills use requires exactly one skill.');
            }

            $skill = $selectedSkills[0];
            $supportDirectory = $this->materializeSkill($skill);
            $prompt = $this->prompt($skill, $supportDirectory);

            return [
                'tool' => 'sift',
                'status' => 'passed',
                'summary' => [
                    'skills' => 1,
                ],
                'items' => [[
                    'name' => $skill->name(),
                    'description' => $skill->description(),
                    'source' => $resolvedSource->source(),
                    'source_type' => $resolvedSource->type(),
                    'path' => $supportDirectory,
                ]],
                'artifacts' => [$supportDirectory],
                'extra' => [
                    'prompt' => $prompt,
                ],
                'meta' => [
                    'subcommand' => 'skills use',
                    'source' => $resolvedSource->source(),
                    'source_type' => $resolvedSource->type(),
                    'resolved_ref' => $resolvedSource->resolvedRef(),
                    'warnings' => $resolvedSource->warnings(),
                ],
            ];
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

    private function skillSelector(CommandRoute $route, SkillSource $source): ?string
    {
        $sourceSelector = $source->requestedSkill();
        $optionSelectors = $this->stringOptionValues($route, 'skill');

        if (count($optionSelectors) > 1) {
            throw new InvalidUsageException('skills use --skill accepts exactly one skill.');
        }

        $optionSelector = $optionSelectors[0] ?? null;

        if ($sourceSelector !== null && $optionSelector !== null && strtolower($sourceSelector) !== strtolower($optionSelector)) {
            throw new InvalidUsageException(sprintf(
                'Conflicting skill selectors: source selects "%s" but --skill selects "%s". Provide one selector.',
                $sourceSelector,
                $optionSelector,
            ));
        }

        return $optionSelector ?? $sourceSelector;
    }

    private function materializeSkill(Skill $skill): string
    {
        $target = Path::join(sys_get_temp_dir(), 'sift-skill-use-' . bin2hex(random_bytes(8)), $skill->name());

        $this->copyDirectory($skill->path(), $target);

        return $target;
    }

    private function prompt(Skill $skill, string $supportDirectory): string
    {
        $contents = file_get_contents($skill->skillFile());

        if (! is_string($contents)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not read skill file "%s".', $skill->skillFile()),
                context: ['path' => $skill->skillFile()],
            );
        }

        return sprintf(
            <<<'PROMPT'
You are being given a Skill to execute for the user's next request.

Use the following SKILL.md as your instructions:

<SKILL.md>
%s
</SKILL.md>

Supporting files for this skill were downloaded to:
%s

When the SKILL.md references relative paths, read them from that directory.
PROMPT,
            rtrim($contents),
            $supportDirectory,
        );
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (! is_dir($source)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Skill source directory "%s" was not found.', $source),
                context: ['path' => $source],
            );
        }

        if (! mkdir($target, 0777, true) && ! is_dir($target)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not create skill use directory "%s".', $target),
                context: ['path' => $target],
            );
        }

        $targetGuard = new PathGuard($target);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            if ($item->isLink()) {
                throw UserFacingException::withContext(
                    errorCode: ErrorCode::FilesystemError,
                    message: sprintf('Skill source symlink "%s" is not allowed.', $item->getPathname()),
                    context: ['path' => $item->getPathname()],
                );
            }

            $relative = substr($item->getPathname(), strlen($source) + 1);
            $destination = $this->guardedDestination($targetGuard, Path::join($target, $relative));

            if ($item->isDir()) {
                if (! mkdir($destination, 0777, true) && ! is_dir($destination)) {
                    throw UserFacingException::withContext(
                        errorCode: ErrorCode::FilesystemError,
                        message: sprintf('Could not create skill use directory "%s".', $destination),
                        context: ['path' => $destination],
                    );
                }

                continue;
            }

            $parent = dirname($destination);

            if (! is_dir($parent) && ! mkdir($parent, 0777, true) && ! is_dir($parent)) {
                throw UserFacingException::withContext(
                    errorCode: ErrorCode::FilesystemError,
                    message: sprintf('Could not create skill use directory "%s".', $parent),
                    context: ['path' => $parent],
                );
            }

            if (! copy($item->getPathname(), $destination)) {
                throw UserFacingException::withContext(
                    errorCode: ErrorCode::FilesystemError,
                    message: sprintf('Could not copy skill file "%s".', $item->getPathname()),
                    context: ['path' => $item->getPathname()],
                );
            }
        }
    }

    private function guardedDestination(PathGuard $guard, string $path): string
    {
        try {
            return $guard->assertInside($path);
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $path],
            );
        }
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
}
