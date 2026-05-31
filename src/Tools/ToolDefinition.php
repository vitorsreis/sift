<?php

declare(strict_types=1);

namespace Sift\Tools;

use InvalidArgumentException;

final readonly class ToolDefinition
{
    /**
     * @param list<string> $aliases
     * @param list<string> $binaryCandidates
     * @param list<string> $versionCommand
     * @param list<string> $repairCommand
     */
    public function __construct(
        private string $name,
        private array $aliases,
        private string $description,
        private array $binaryCandidates,
        private string $installHint,
        private string $defaultContext,
        private array $versionCommand = [],
        private MutationPolicy $mutationPolicy = MutationPolicy::Never,
        private array $repairCommand = [],
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Tool definition name cannot be empty.');
        }

        if ($binaryCandidates === []) {
            throw new InvalidArgumentException('Tool definition must include at least one binary candidate.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function aliases(): array
    {
        return $this->aliases;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return list<string>
     */
    public function binaryCandidates(): array
    {
        return $this->binaryCandidates;
    }

    /**
     * @return list<string>
     */
    public function versionCommand(): array
    {
        return $this->versionCommand;
    }

    public function installHint(): string
    {
        return $this->installHint;
    }

    public function defaultContext(): string
    {
        return $this->defaultContext;
    }

    public function mutationPolicy(): MutationPolicy
    {
        return $this->mutationPolicy;
    }

    /**
     * @return list<string>
     */
    public function repairCommand(): array
    {
        return $this->repairCommand;
    }
}
