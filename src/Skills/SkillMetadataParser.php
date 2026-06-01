<?php

declare(strict_types=1);

namespace Sift\Skills;

use Sift\Console\InvalidUsageException;
use Sift\Filesystem\Path;

final readonly class SkillMetadataParser
{
    public function parse(string $skillFile, string $path, string $source, string $sourceType): Skill
    {
        $contents = file_get_contents($skillFile);

        if (! is_string($contents) || trim($contents) === '') {
            throw new InvalidUsageException(sprintf('Skill file "%s" is empty.', $skillFile));
        }

        $frontmatter = $this->frontmatter($contents);
        $name = $frontmatter['name'] ?? null;

        if (! is_string($name) || preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $name) !== 1) {
            throw new InvalidUsageException(sprintf('Skill file "%s" must define a valid name.', $skillFile));
        }

        $description = $frontmatter['description'] ?? null;

        return new Skill(
            name: $name,
            description: is_string($description) && trim($description) !== '' ? trim($description) : sprintf('Use the %s skill.', $name),
            path: Path::normalize($path),
            skillFile: Path::normalize($skillFile),
            source: $source,
            sourceType: $sourceType,
        );
    }

    /**
     * @return array<string, string>
     */
    private function frontmatter(string $contents): array
    {
        if (preg_match('/^---\R(?P<body>.*?)\R---/s', $contents, $matches) !== 1) {
            return [];
        }

        $lines = preg_split('/\R/', $matches['body']);

        if ($lines === false) {
            return [];
        }

        $values = [];
        $count = count($lines);
        $index = 0;

        while ($index < $count) {
            $line = $lines[$index];

            if (preg_match('/^(?P<key>[A-Za-z0-9_-]+):\s*(?P<value>.*)$/', $line, $lineMatches) !== 1) {
                ++$index;
                continue;
            }

            $key = $lineMatches['key'];
            $value = trim($lineMatches['value'], " \t\"'");

            if ($value === '>') {
                [$value, $index] = $this->foldedValue($lines, $index + 1);
                $values[$key] = $value;
                continue;
            }

            $values[$key] = $value;
            ++$index;
        }

        return $values;
    }

    /**
     * @param list<string> $lines
     *
     * @return array{string, int}
     */
    private function foldedValue(array $lines, int $index): array
    {
        $parts = [];
        $count = count($lines);

        while ($index < $count) {
            $line = $lines[$index];

            if (preg_match('/^[A-Za-z0-9_-]+:/', $line) === 1) {
                break;
            }

            $trimmed = trim($line);

            if ($trimmed !== '') {
                $parts[] = $trimmed;
            }

            ++$index;
        }

        return [implode(' ', $parts), $index];
    }
}
