<?php

declare(strict_types=1);

namespace Sift\Tools\Composer;

use Sift\Core\ExecutionResult;

final readonly class ComposerValidateParser
{
    public function parse(ExecutionResult $execution): ComposerReport
    {
        $lines = $this->lines($execution->stdout(), $execution->stderr());
        $items = $this->items($lines);
        $errors = $this->countSeverity($items, 'error');
        $warnings = $this->countSeverity($items, 'warning');
        $findings = $execution->exitCode() === 1 || $execution->exitCode() === 2
            ? max(1, count($items))
            : count($items);

        return new ComposerReport(
            summary: [
                'valid' => $errors === 0 && $execution->exitCode() !== 2,
                'errors' => $errors,
                'warnings' => $warnings,
                'findings' => $findings,
            ],
            items: $items,
            findings: $findings,
            extra: [
                'output' => $lines,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function lines(string $stdout, string $stderr): array
    {
        $output = trim($stdout . "\n" . $stderr);

        if ($output === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), preg_split('/\R/', $output) ?: []),
            static fn(string $line): bool => $line !== '',
        ));
    }

    /**
     * @param list<string> $lines
     *
     * @return list<array{severity: string, message: string}>
     */
    private function items(array $lines): array
    {
        $severity = 'info';
        $items = [];

        foreach ($lines as $line) {
            $lower = strtolower($line);

            if (str_contains($lower, 'error')) {
                $severity = 'error';
                continue;
            }

            if (str_contains($lower, 'warning')) {
                $severity = 'warning';
                continue;
            }

            if (! str_starts_with($line, '- ')) {
                continue;
            }

            $items[] = [
                'severity' => $severity,
                'message' => substr($line, 2),
            ];
        }

        return $items;
    }

    /**
     * @param list<array{severity: string, message: string}> $items
     */
    private function countSeverity(array $items, string $severity): int
    {
        return count(array_filter(
            $items,
            static fn(array $item): bool => $item['severity'] === $severity,
        ));
    }
}
