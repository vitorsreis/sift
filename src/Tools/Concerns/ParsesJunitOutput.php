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
                $items[] = $this->junitItem('failure', $testName, $className, $resolvedFile, $failure, $preparedCommand->cwd);
            }

            foreach ($testCase->error as $error) {
                $errors++;
                $items[] = $this->junitItem('error', $testName, $className, $resolvedFile, $error, $preparedCommand->cwd);
            }

            foreach ($testCase->skipped as $skip) {
                $skipped++;
                $items[] = $this->junitItem('skipped', $testName, $className, $resolvedFile, $skip, $preparedCommand->cwd);
            }
        }

        return new NormalizedResult(
            tool: $toolName,
            status: ($failures > 0 || $errors > 0) ? 'failed' : 'passed',
            summary: [
                'tests' => $tests,
                'passed' => max(0, $tests - $failures - $errors - $skipped),
                'failures' => $failures,
                'errors' => $errors,
                'skipped' => $skipped,
            ],
            items: $items,
            meta: [
                'exit_code' => $executionResult->exitCode,
                'duration' => $executionResult->duration,
                'command' => $preparedCommand->command,
                'filter' => (bool) ($context['has_filter'] ?? false),
                'coverage' => (bool) ($context['has_coverage'] ?? false),
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
        string $className,
        ?string $resolvedFile,
        SimpleXMLElement $node,
        string $cwd,
    ): array {
        $message = trim((string) ($node['message'] ?? ''));
        $details = trim((string) $node);
        $combined = $this->combineJunitMessage($message, $details);
        [$reportedFile, $reportedLine] = $this->extractJunitLocation($combined, $cwd);

        $item = [
            'type' => $type,
            'test' => $testName,
            'class' => $className,
            'file' => $reportedFile ?? $resolvedFile,
            'message' => $combined,
        ];

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
}
