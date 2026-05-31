<?php

declare(strict_types=1);

namespace Sift\Core;

use InvalidArgumentException;

final readonly class PreparedCommand
{
    /**
     * @var non-empty-list<string>|null
     */
    private ?array $displayCommand;

    /**
     * @param list<string> $arguments
     * @param array<string, string> $environment
     * @param list<string> $temporaryFiles
     * @param array<string, string> $artifacts
     * @param list<string>|null $displayCommand
     */
    public function __construct(
        private string $tool,
        private string $binary,
        private array $arguments = [],
        private string $cwd = '.',
        private array $environment = [],
        private int $timeout = 0,
        private array $temporaryFiles = [],
        private array $artifacts = [],
        ?array $displayCommand = null,
        private string $nativeOutputMode = 'capture',
    ) {
        if (trim($tool) === '') {
            throw new InvalidArgumentException('Prepared command tool cannot be empty.');
        }

        if (trim($binary) === '') {
            throw new InvalidArgumentException('Prepared command binary cannot be empty.');
        }

        if ($timeout < 0) {
            throw new InvalidArgumentException('Prepared command timeout cannot be negative.');
        }

        $this->validateStringList($temporaryFiles, 'temporary file path');
        $this->validateStringMap($artifacts, 'artifact');

        if ($displayCommand !== null) {
            if ($displayCommand === []) {
                throw new InvalidArgumentException('Prepared command display command cannot be empty.');
            }

            $this->validateStringList($displayCommand, 'display command token');
        }

        if (! in_array($nativeOutputMode, ['capture', 'stream', 'inherit'], true)) {
            throw new InvalidArgumentException('Prepared command native output mode is invalid.');
        }

        $this->displayCommand = $displayCommand;
    }

    public function tool(): string
    {
        return $this->tool;
    }

    public function binary(): string
    {
        return $this->binary;
    }

    /**
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return non-empty-list<string>
     */
    public function argv(): array
    {
        return [$this->binary, ...$this->arguments];
    }

    public function cwd(): string
    {
        return $this->cwd;
    }

    /**
     * @return array<string, string>
     */
    public function environment(): array
    {
        return $this->environment;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    /**
     * @return list<string>
     */
    public function temporaryFiles(): array
    {
        return $this->temporaryFiles;
    }

    /**
     * @return array<string, string>
     */
    public function artifacts(): array
    {
        return $this->artifacts;
    }

    /**
     * @return non-empty-list<string>
     */
    public function displayCommand(): array
    {
        return $this->displayCommand ?? $this->argv();
    }

    public function nativeOutputMode(): string
    {
        return $this->nativeOutputMode;
    }

    /**
     * @param list<string> $values
     */
    private function validateStringList(array $values, string $name): void
    {
        foreach ($values as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Prepared command %s cannot be empty.', $name));
            }
        }
    }

    /**
     * @param array<string, string> $values
     */
    private function validateStringMap(array $values, string $name): void
    {
        foreach ($values as $key => $value) {
            if (trim((string) $key) === '' || trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Prepared command %s entry cannot be empty.', $name));
            }
        }
    }
}
