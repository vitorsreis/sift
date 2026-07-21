<?php

declare(strict_types=1);

namespace Sift\Tools\ComposerNormalize;

use Sift\Core\ItemType;
use Sift\Tools\Testing\ReportPathNormalizer;

final readonly class ComposerNormalizeParser
{
    public function __construct(
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): ComposerNormalizeReport
    {
        $output = trim(implode(PHP_EOL, array_filter([$stdout, $stderr], static fn(string $value): bool => trim($value) !== '')));
        $notNormalized = preg_match('/\bis not normalized\b/i', $output) === 1;
        $diff = $this->diff($output);

        if (! $notNormalized && $diff === null) {
            return new ComposerNormalizeReport(
                summary: ['files' => 0, 'diffs' => 0],
                items: [],
                files: 0,
            );
        }

        $file = $this->pathNormalizer->normalize($this->file($output), $cwd);
        $items = [[
            'type' => ItemType::Issue->value,
            'file' => $file,
            'message' => sprintf('%s is not normalized.', $file),
        ]];

        if ($diff !== null) {
            $items[] = [
                'type' => ItemType::Diff->value,
                'file' => $file,
                'diff' => $diff,
            ];
        }

        return new ComposerNormalizeReport(
            summary: ['files' => 1, 'diffs' => $diff === null ? 0 : 1],
            items: $items,
            files: 1,
        );
    }

    private function file(string $output): string
    {
        if (preg_match('/^["\']?(?<file>[^\r\n"\']+\.json)["\']?\s+is not normalized\b/im', $output, $matches) === 1) {
            $file = trim($matches['file']);

            if ($file !== '') {
                return preg_replace('#^\./#', '', $file) ?? $file;
            }
        }

        return 'composer.json';
    }

    private function diff(string $output): ?string
    {
        if (preg_match('/^---\s.+$/m', $output, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $matches[0][1];

        $diff = trim(substr($output, $offset));

        return $diff === '' ? null : $diff;
    }
}
