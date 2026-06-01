<?php

declare(strict_types=1);

namespace Sift\Skills;

use Sift\Console\InvalidUsageException;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\AtomicWriter;
use Sift\Filesystem\FilesystemException;
use Sift\Filesystem\Path;
use Sift\Filesystem\PathGuard;

final readonly class SkillScaffolder
{
    public function __construct(
        private AtomicWriter $writer = new AtomicWriter(),
    ) {}

    /**
     * @return array{name: string, path: string, action: string}
     */
    public function scaffold(string $cwd, ?string $name, bool $overwrite = false): array
    {
        $skillName = $this->skillName($cwd, $name);
        $path = $this->skillFilePath($cwd, $name, $skillName);
        $exists = is_file($path);

        if ($exists && ! $overwrite) {
            throw new InvalidUsageException('SKILL.md already exists. Use --yes to overwrite it.');
        }

        try {
            $this->writer->write($path, $this->template($skillName));
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $path],
            );
        }

        return [
            'name' => $skillName,
            'path' => $path,
            'action' => $exists ? 'updated' : 'created',
        ];
    }

    private function skillName(string $cwd, ?string $name): string
    {
        $source = is_string($name) && trim($name) !== '' ? $name : basename(Path::normalize($cwd));
        $slug = $this->slug($source);

        if (preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $slug) !== 1) {
            throw new InvalidUsageException('Unable to infer a valid skill name.');
        }

        return $slug;
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        return is_string($slug) ? trim($slug, '-') : '';
    }

    private function skillFilePath(string $cwd, ?string $requestedName, string $skillName): string
    {
        $path = is_string($requestedName) && trim($requestedName) !== ''
            ? Path::join($cwd, $skillName, 'SKILL.md')
            : Path::join($cwd, 'SKILL.md');

        try {
            return (new PathGuard($cwd))->assertInside($path);
        } catch (FilesystemException $filesystemException) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: $filesystemException->getMessage(),
                context: ['path' => $path],
            );
        }
    }

    private function template(string $skillName): string
    {
        $words = str_replace('-', ' ', $skillName);
        $title = ucwords($words);

        return <<<MD
---
name: {$skillName}
description: Use when working with {$words}.
---

# {$title}

Use this skill when working with {$words}.

## Workflow

1. Inspect the relevant project files.
2. Apply the existing project conventions.
3. Verify the result before reporting completion.
MD . PHP_EOL;
    }
}
