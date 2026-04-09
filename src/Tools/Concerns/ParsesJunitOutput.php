<?php

declare(strict_types=1);

namespace Sift\Tools\Concerns;

use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use SimpleXMLElement;

trait ParsesJunitOutput
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function parseJunitOutput(
        ExecutionResult $executionResult,
        PreparedCommand $preparedCommand,
        array $context,
    ): NormalizedResult {
        $toolName = $this->name();
        $junitPath = (string) ($preparedCommand->metadata['junit'] ?? '');

        if ($junitPath === '' || ! is_file($junitPath)) {
            throw UserFacingException::parseFailure($toolName, 'Missing or invalid JUnit output.');
        }

        $xml = simplexml_load_file($junitPath);

        if (! $xml instanceof SimpleXMLElement) {
            throw UserFacingException::parseFailure($toolName, 'Unable to parse JUnit XML output.');
        }

        $items = [];
        $tests = 0;
        $failures = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($xml->xpath('//testcase') ?: [] as $testCase) {
            if (! $testCase instanceof SimpleXMLElement) {
                continue;
            }

            $tests++;
            $testName = (string) ($testCase['name'] ?? 'unknown');
            $className = (string) ($testCase['class'] ?? '');
            $resolvedFile = $this->resolveJunitFile($testCase, $className, $preparedCommand->cwd);

            foreach ($testCase->failure as $failure) {
                $failures++;
                $items[] = $this->junitItem('failure', $testName, $resolvedFile, $failure, $preparedCommand->cwd);
            }

            foreach ($testCase->error as $error) {
                $errors++;
                $items[] = $this->junitItem('error', $testName, $resolvedFile, $error, $preparedCommand->cwd);
            }

            foreach ($testCase->skipped as $skip) {
                $skipped++;
                $items[] = $this->junitItem('skipped', $testName, $resolvedFile, $skip, $preparedCommand->cwd);
            }
        }

        $coverage = $this->parseCoverageReport($toolName, $preparedCommand, $context);
        $summary = [
            'tests' => $tests,
            'passed' => max(0, $tests - $failures - $errors - $skipped),
            'failures' => $failures,
            'errors' => $errors,
            'skipped' => $skipped,
        ];
        if ($coverage !== null) {
            $summary['coverage_percent'] = $coverage['percent'];

            if ($coverage['minimum'] !== null) {
                $summary['coverage_min'] = $coverage['minimum'];
                $summary['coverage_files_below_min'] = count($coverage['files_below_min']);
            }

            if ($coverage['files_below_min'] !== []) {
                $items = [...$items, ...$this->coverageItems($coverage['files_below_min'])];
            }
        }

        $status = ($failures > 0 || $errors > 0 || ($coverage['threshold_failed'] ?? false) === true || $executionResult->exitCode !== 0)
            ? 'failed'
            : 'passed';

        return new NormalizedResult(
            tool: $toolName,
            status: $status,
            summary: $summary,
            items: $items,
            meta: [
                'exit_code' => $executionResult->exitCode,
                'duration' => $executionResult->duration,
                'command' => $preparedCommand->command,
                'filter' => (bool) ($context['has_filter'] ?? false),
                'coverage' => (bool) ($context['has_coverage'] ?? false),
                'coverage_min' => $coverage['minimum'] ?? null,
            ],
        );
    }

    private function resolveJunitFile(SimpleXMLElement $testCase, string $className, string $cwd): ?string
    {
        $candidate = trim((string) ($testCase['file'] ?? ''));

        if ($candidate !== '') {
            return $this->normalizeJunitPath($candidate, $cwd);
        }

        return $className !== '' ? str_replace('\\', '/', $className) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function junitItem(
        string $type,
        string $testName,
        ?string $resolvedFile,
        SimpleXMLElement $node,
        string $cwd,
    ): array {
        $message = trim((string) ($node['message'] ?? ''));
        $details = trim((string) $node);
        $combined = $this->combineJunitMessage($message, $details);
        [$reportedFile, $reportedLine] = $this->extractJunitLocation($combined, $cwd);
        $sanitizedMessage = $this->sanitizeJunitMessage($combined);

        $item = [
            'type' => $type,
            'test' => $testName,
            'file' => $reportedFile ?? $resolvedFile,
        ];

        if ($sanitizedMessage !== '') {
            $item['message'] = $sanitizedMessage;
        }

        if ($reportedLine !== null) {
            $item['line'] = $reportedLine;
        }

        return $item;
    }

    private function combineJunitMessage(string $message, string $details): string
    {
        if ($message === '') {
            return $details;
        }

        if ($details === '' || $details === $message) {
            return $message;
        }

        if (str_contains($details, $message)) {
            return $details;
        }

        return $message."\n".$details;
    }

    private function sanitizeJunitMessage(string $message): string
    {
        if ($message === '') {
            return '';
        }

        $normalized = preg_replace('~\r\n?~', "\n", $message);

        if (! is_string($normalized)) {
            return '';
        }

        $normalized = preg_replace('~^at\s+.+?\.php:\d+\s*$~mi', '', $normalized);

        if (! is_string($normalized)) {
            return '';
        }

        $normalized = preg_replace("~\n{3,}~", "\n\n", $normalized);

        if (! is_string($normalized)) {
            return '';
        }

        return trim($normalized);
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function extractJunitLocation(string $message, string $cwd): array
    {
        if (preg_match('~\bat\s+(.+?\.php):(\d+)\b~', $message, $matches) === 1) {
            return [
                $this->normalizeJunitPath((string) $matches[1], $cwd),
                (int) $matches[2],
            ];
        }

        return [null, null];
    }

    private function normalizeJunitPath(string $path, string $cwd): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $separator = strpos($normalized, '::');

        if ($separator !== false) {
            $normalized = substr($normalized, 0, $separator);
        }

        $cwd = rtrim(str_replace('\\', '/', $cwd), '/');
        $lowerNormalized = strtolower($normalized);
        $lowerCwd = strtolower($cwd);

        if ($cwd !== '' && $lowerNormalized === $lowerCwd) {
            return '';
        }

        if ($cwd !== '' && str_starts_with($lowerNormalized, $lowerCwd.'/')) {
            return ltrim(substr($normalized, strlen($cwd)), '/');
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *   percent: float,
     *   minimum: ?float,
     *   threshold_failed: bool,
     *   files_below_min: list<array{file: string, percent: float}>
     * }|null
     */
    private function parseCoverageReport(string $toolName, PreparedCommand $preparedCommand, array $context): ?array
    {
        $coveragePath = (string) ($preparedCommand->metadata['coverage_clover'] ?? '');

        if ($coveragePath === '') {
            return null;
        }

        if (! is_file($coveragePath)) {
            throw UserFacingException::parseFailure($toolName, 'Missing or invalid Clover coverage output.');
        }

        $xml = simplexml_load_file($coveragePath);

        if (! $xml instanceof SimpleXMLElement) {
            throw UserFacingException::parseFailure($toolName, 'Unable to parse Clover coverage output.');
        }

        $projectMetrics = $xml->xpath('/coverage/project/metrics') ?: $xml->xpath('//project/metrics');
        $percent = $this->coveragePercentFromMetrics($projectMetrics[0] ?? null);

        if ($percent === null) {
            return null;
        }

        $minimum = is_numeric($context['coverage_min'] ?? null)
            ? round((float) $context['coverage_min'], 2)
            : null;
        $filesBelowMin = [];

        foreach ($xml->xpath('//file') ?: [] as $fileNode) {
            if (! $fileNode instanceof SimpleXMLElement) {
                continue;
            }

            $filePath = trim((string) ($fileNode['name'] ?? ''));

            if ($filePath === '') {
                continue;
            }

            $filePercent = $this->coveragePercentFromMetrics($fileNode->metrics[0] ?? null);

            if ($filePercent === null) {
                continue;
            }

            if ($minimum === null || $filePercent + 0.00001 >= $minimum) {
                continue;
            }

            $filesBelowMin[] = [
                'file' => $this->normalizeJunitPath($filePath, $preparedCommand->cwd),
                'percent' => $filePercent,
            ];
        }

        usort(
            $filesBelowMin,
            static fn (array $left, array $right): int => ($left['percent'] <=> $right['percent'])
                ?: strcmp($left['file'], $right['file']),
        );

        return [
            'percent' => $percent,
            'minimum' => $minimum,
            'threshold_failed' => $minimum !== null && $percent + 0.00001 < $minimum,
            'files_below_min' => $filesBelowMin,
        ];
    }

    private function coveragePercentFromMetrics(mixed $metrics): ?float
    {
        if (! $metrics instanceof SimpleXMLElement) {
            return null;
        }

        foreach ([
            ['coveredstatements', 'statements'],
            ['coveredelements', 'elements'],
            ['coveredmethods', 'methods'],
        ] as [$coveredKey, $totalKey]) {
            $covered = (int) ($metrics[$coveredKey] ?? 0);
            $total = (int) ($metrics[$totalKey] ?? 0);

            if ($total <= 0) {
                continue;
            }

            return round(($covered / $total) * 100, 2);
        }

        return null;
    }

    /**
     * @param  list<array{file: string, percent: float}>  $filesBelowMin
     * @return list<array<string, mixed>>
     */
    private function coverageItems(array $filesBelowMin): array
    {
        $items = [];

        foreach ($filesBelowMin as $entry) {
            $items[] = [
                'type' => 'coverage',
                'file' => $entry['file'],
                'percent' => $entry['percent'],
            ];
        }

        return $items;
    }
}
