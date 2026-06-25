<?php

declare(strict_types=1);

namespace Sift\Tools\PhpCs;

use Sift\Core\ItemType;
use Sift\Tools\Testing\ReportPathNormalizer;

final readonly class PhpcbfParser
{
    public function __construct(
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): PhpcbfReport
    {
        $output = trim($stdout . PHP_EOL . $stderr);

        if (stripos($output, 'No violations were found') !== false) {
            return $this->report('passed', [], 0, 0, recognized: true);
        }

        $items = [];
        $fixed = 0;
        $remaining = 0;

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $row = $this->tableRow($line, $cwd);

            if ($row === null) {
                continue;
            }

            $fixed += $row['fixed'];
            $remaining += $row['remaining'];
            $items[] = $row;
        }

        if ($items === []) {
            return $this->report('unknown', [], 0, 0, recognized: false);
        }

        return $this->report(
            result: $remaining > 0 ? 'remaining' : 'fixed',
            items: $items,
            fixed: $fixed,
            remaining: $remaining,
            recognized: true,
        );
    }

    /**
     * @return array{type: string, file: string, fixed: int, remaining: int}|null
     */
    private function tableRow(string $line, string $cwd): ?array
    {
        if (preg_match('/^(?<file>.+?)\s+(?<fixed>\d+)\s+(?<remaining>\d+)\s*$/', trim($line), $matches) !== 1) {
            return null;
        }

        $fixed = (int) $matches['fixed'];
        $remaining = (int) $matches['remaining'];

        return [
            'type' => ItemType::ChangedFile->value,
            'file' => $this->pathNormalizer->normalize($matches['file'], $cwd),
            'fixed' => $fixed,
            'remaining' => $remaining,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function report(string $result, array $items, int $fixed, int $remaining, bool $recognized): PhpcbfReport
    {
        return new PhpcbfReport(
            summary: [
                'result' => $result,
                'files' => count($items),
                'fixed' => $fixed,
                'remaining' => $remaining,
            ],
            items: $items,
            result: $result,
            files: count($items),
            fixed: $fixed,
            remaining: $remaining,
            recognized: $recognized,
        );
    }
}
