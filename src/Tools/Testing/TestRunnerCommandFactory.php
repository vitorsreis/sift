<?php

declare(strict_types=1);

namespace Sift\Tools\Testing;

use Sift\Config\ToolConfig;
use Sift\Console\InvalidUsageException;
use Sift\Core\PreparedCommand;
use Sift\Execution\LocatedTool;
use Sift\Filesystem\Path;
use Sift\Filesystem\TempFileFactory;
use Sift\Tools\ToolContext;

final readonly class TestRunnerCommandFactory
{
    public function __construct(
        private TempFileFactory $tempFileFactory,
    ) {}

    /**
     * @param list<string> $arguments
     */
    public function prepare(
        string $toolName,
        LocatedTool $tool,
        ToolContext $context,
        ToolConfig $config,
        array $arguments,
        string $junitOption = '--log-junit',
        string $coverageOption = '--coverage-clover',
        bool $inlineOutputOptions = false,
    ): PreparedCommand {
        $temporaryFiles = [];
        $artifacts = [];

        $junitPath = $this->optionValue($arguments, $junitOption);

        if ($junitPath === null) {
            $tempFile = $this->tempFileFactory->create('sift-junit-', '.xml');
            $junitPath = $tempFile->path();
            $temporaryFiles[] = $junitPath;
            $arguments = $this->appendOutputOption($arguments, $junitOption, $junitPath, $inlineOutputOptions);
        }

        $artifacts['junit'] = $this->artifactPath($junitPath, $context->cwd());

        if ($context->coverage() || $context->coverageMin() !== null) {
            $cloverPath = $this->optionValue($arguments, $coverageOption);

            if ($cloverPath === null) {
                $tempFile = $this->tempFileFactory->create('sift-clover-', '.xml');
                $cloverPath = $tempFile->path();
                $temporaryFiles[] = $cloverPath;
                $arguments = $this->appendOutputOption($arguments, $coverageOption, $cloverPath, $inlineOutputOptions);
            }

            $artifacts['coverage_clover'] = $this->artifactPath($cloverPath, $context->cwd());
        }

        return new PreparedCommand(
            tool: $toolName,
            binary: $tool->binary(),
            arguments: $arguments,
            cwd: $context->cwd(),
            timeout: $config->timeout(),
            temporaryFiles: $temporaryFiles,
            artifacts: $artifacts,
        );
    }

    /**
     * @param list<string> $arguments
     */
    private function optionValue(array $arguments, string $option): ?string
    {
        foreach ($arguments as $index => $argument) {
            if (str_starts_with($argument, $option . '=')) {
                $value = substr($argument, strlen($option) + 1);

                if ($value === '') {
                    throw new InvalidUsageException(sprintf('Argument "%s" requires a value.', $option));
                }

                return $value;
            }

            if ($argument !== $option) {
                continue;
            }

            $value = $arguments[$index + 1] ?? null;

            if ($value === null || str_starts_with($value, '-')) {
                throw new InvalidUsageException(sprintf('Argument "%s" requires a value.', $option));
            }

            return $value;
        }

        return null;
    }

    private function artifactPath(string $path, string $cwd): string
    {
        return Path::isAbsolute($path) ? Path::normalize($path) : Path::join($cwd, $path);
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function appendOutputOption(array $arguments, string $option, string $path, bool $inline): array
    {
        return $inline
            ? [...$arguments, $option . '=' . $path]
            : [...$arguments, $option, $path];
    }
}
