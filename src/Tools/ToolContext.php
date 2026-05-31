<?php

declare(strict_types=1);

namespace Sift\Tools;

use InvalidArgumentException;
use Sift\Config\SiftConfig;
use Sift\Console\OutputPreferences;

final readonly class ToolContext
{
    /**
     * @param list<string> $userArgs
     * @param list<string> $warnings
     */
    public function __construct(
        private string $toolName,
        private ?string $subcommand = null,
        private array $userArgs = [],
        private string $cwd = '.',
        private ?SiftConfig $config = null,
        private ?OutputPreferences $outputPreferences = null,
        private bool $raw = false,
        private bool $debug = false,
        private bool $repair = false,
        private bool $dryRun = false,
        private ?string $filter = null,
        private bool $coverage = false,
        private ?float $coverageMin = null,
        private ?string $mode = null,
        private array $warnings = [],
    ) {
        if (trim($toolName) === '') {
            throw new InvalidArgumentException('Tool context name cannot be empty.');
        }
    }

    public function toolName(): string
    {
        return $this->toolName;
    }

    public function subcommand(): ?string
    {
        return $this->subcommand;
    }

    /**
     * @return list<string>
     */
    public function userArgs(): array
    {
        return $this->userArgs;
    }

    public function cwd(): string
    {
        return $this->cwd;
    }

    public function config(): ?SiftConfig
    {
        return $this->config;
    }

    public function outputPreferences(): ?OutputPreferences
    {
        return $this->outputPreferences;
    }

    public function raw(): bool
    {
        return $this->raw;
    }

    public function debug(): bool
    {
        return $this->debug;
    }

    public function repair(): bool
    {
        return $this->repair;
    }

    public function dryRun(): bool
    {
        return $this->dryRun;
    }

    public function filter(): ?string
    {
        return $this->filter;
    }

    public function coverage(): bool
    {
        return $this->coverage;
    }

    public function coverageMin(): ?float
    {
        return $this->coverageMin;
    }

    public function mode(): ?string
    {
        return $this->mode;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
