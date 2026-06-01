<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\AtomicWriter;
use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\Path;
use Sift\Filesystem\PathGuard;
use Sift\Skills\ManagedBlockEditor;
use Sift\Skills\Targets\InstructionTargetRegistry;

final readonly class SkillsRemoveCommand implements CommandHandler
{
    public function __construct(
        private ManagedBlockEditor $blockEditor = new ManagedBlockEditor(),
        private InstructionTargetRegistry $targetRegistry = new InstructionTargetRegistry(),
        private AtomicWriter $writer = new AtomicWriter(),
    ) {}

    public function handle(CommandRoute $route, string $cwd): array
    {
        $this->assertConfirmed($route);

        $skillName = $this->skillName($route);
        $targets = $this->targets($route);
        $items = [];

        foreach ($targets as $target) {
            $this->targetRegistry->resolve($target);

            if ($target !== 'generic') {
                continue;
            }

            $items[] = $this->removeFromInstructionFile($cwd, 'AGENTS.md', $skillName, $target);
        }

        $removed = count(array_filter($items, static fn(array $item): bool => ($item['action'] ?? null) === 'removed'));

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'removed' => $removed,
            ],
            'items' => $items,
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'skills remove',
                'targets' => $targets,
            ],
        ];
    }

    private function assertConfirmed(CommandRoute $route): void
    {
        if (($route->options()['yes'] ?? false) === true || ($route->options()['all'] ?? false) === true) {
            return;
        }

        throw new InvalidUsageException('Mutating skill commands require --yes or --all in non-interactive mode.');
    }

    private function skillName(CommandRoute $route): string
    {
        $argument = $route->arguments()[0] ?? null;
        $option = $route->options()['skill'] ?? null;
        $name = is_string($argument) ? $argument : $option;

        if (! is_string($name) || preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $name) !== 1) {
            throw new InvalidUsageException('skills remove requires a skill name.');
        }

        return $name;
    }

    /**
     * @return list<string>
     */
    private function targets(CommandRoute $route): array
    {
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

    /**
     * @return array<string, mixed>
     */
    private function removeFromInstructionFile(string $cwd, string $relativePath, string $skillName, string $target): array
    {
        $path = $this->targetPath($cwd, $relativePath);

        if (! is_file($path)) {
            return [
                'name' => $skillName,
                'target' => $target,
                'path' => $path,
                'action' => 'missing',
            ];
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not read target file "%s".', $path),
                context: ['path' => $path],
            );
        }

        $next = $this->blockEditor->remove($contents, $skillName);
        $action = $next === $contents ? 'missing' : 'removed';

        if ($action === 'removed') {
            try {
                $this->writer->write($path, $next);
            } catch (FilesystemException $filesystemException) {
                throw UserFacingException::withContext(
                    errorCode: ErrorCode::FilesystemError,
                    message: $filesystemException->getMessage(),
                    context: ['path' => $path],
                );
            }
        }

        return [
            'name' => $skillName,
            'target' => $target,
            'path' => $path,
            'action' => $action,
        ];
    }

    private function targetPath(string $cwd, string $relativePath): string
    {
        try {
            return (new PathGuard($cwd))->assertInside(Path::join($cwd, $relativePath));
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $relativePath],
            );
        }
    }
}
