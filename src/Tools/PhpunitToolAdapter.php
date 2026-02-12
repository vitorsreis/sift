<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;
use SimpleXMLElement;

final readonly class PhpunitToolAdapter implements ToolAdapterInterface
{
    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function name(): string
    {
        return 'phpunit';
    }

    public function installHint(): string
    {
        return 'Install it with: composer require --dev phpunit/phpunit';
    }

    /**
     * @return list<string>
     */
    public function discoveryCandidates(): array
    {
        return [
            'vendor/bin/phpunit.bat',
            'vendor/bin/phpunit',
            'phpunit',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function initConfig(): array
    {
        return [
            'enabled' => true,
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    public function detectContext(array $arguments): array
    {
        return [
            'arguments' => $arguments,
            'has_filter' => $this->hasOption($arguments, '--filter'),
            'has_coverage' => $this->hasOption($arguments, '--coverage-text')
                || $this->hasOption($arguments, '--coverage-clover')
                || $this->hasOption($arguments, '--coverage-html'),
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, mixed>  $context
     */
    public function prepare(string $cwd, array $arguments, array $context): PreparedCommand
    {
        $resolved = $this->toolLocator->locate($cwd, $this->discoveryCandidates());

        if ($resolved === null) {
            throw UserFacingException::toolNotInstalled($this->name(), $this->installHint());
        }

        [$arguments, $junitPath] = $this->ensureOptionValue(
            $arguments,
            '--log-junit',
            $this->tempFile('sift-phpunit-', '.xml'),
        );

        return new PreparedCommand(
            command: [...$resolved['command_prefix'], ...$arguments],
            cwd: $cwd,
            metadata: [
                ...$context,
                'junit' => $junitPath,
                'temp_files' => [$junitPath],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function parse(
        ExecutionResult $executionResult,
        PreparedCommand $preparedCommand,
        array $context,
    ): NormalizedResult {
        $junitPath = (string) ($preparedCommand->metadata['junit'] ?? '');

        if ($junitPath === '' || ! is_file($junitPath)) {
            throw UserFacingException::parseFailure($this->name(), 'Missing or invalid JUnit output.');
        }

        $xml = simplexml_load_file($junitPath);

        if (! $xml instanceof SimpleXMLElement) {
            throw UserFacingException::parseFailure($this->name(), 'Unable to parse JUnit XML output.');
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
            $file = ($className !== '' ? str_replace('\\', '/', $className) : null);

            foreach ($testCase->failure as $failure) {
                $failures++;
                $items[] = [
                    'type' => 'failure',
                    'test' => $testName,
                    'class' => $className,
                    'file' => $file,
                    'message' => trim((string) ($failure['message'] ?? (string) $failure)),
                ];
            }

            foreach ($testCase->error as $error) {
                $errors++;
                $items[] = [
                    'type' => 'error',
                    'test' => $testName,
                    'class' => $className,
                    'file' => $file,
                    'message' => trim((string) ($error['message'] ?? (string) $error)),
                ];
            }

            foreach ($testCase->skipped as $skip) {
                $skipped++;
                $items[] = [
                    'type' => 'skipped',
                    'test' => $testName,
                    'class' => $className,
                    'file' => $file,
                    'message' => trim((string) ($skip['message'] ?? (string) $skip)),
                ];
            }
        }

        return new NormalizedResult(
            tool: $this->name(),
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

    /**
     * @param  list<string>  $arguments
     */
    private function hasOption(array $arguments, string $option): bool
    {
        foreach ($arguments as $argument) {
            if ($argument === $option || str_starts_with($argument, $option.'=')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $arguments
     * @return array{0: list<string>, 1: string}
     */
    private function ensureOptionValue(array $arguments, string $option, string $defaultValue): array
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === $option && isset($arguments[$index + 1])) {
                return [$arguments, $arguments[$index + 1]];
            }

            if (str_starts_with($argument, $option.'=')) {
                return [$arguments, substr($argument, strlen($option) + 1)];
            }
        }

        $arguments[] = $option;
        $arguments[] = $defaultValue;

        return [$arguments, $defaultValue];
    }

    private function tempFile(string $prefix, string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw UserFacingException::parseFailure($this->name(), 'Unable to allocate temporary file.');
        }

        $target = $path.$extension;
        @rename($path, $target);

        return $target;
    }
}
