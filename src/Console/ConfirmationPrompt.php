<?php

declare(strict_types=1);

namespace Sift\Console;

use Closure;
use Sift\Output\TerminalStyle;

final readonly class ConfirmationPrompt
{
    public function __construct(
        private ?Closure $interactive = null,
        private ?Closure $reader = null,
        private ?Closure $writer = null,
    ) {}

    public function confirm(string $message, bool $color = true): void
    {
        $this->assertInteractive();

        $style = new TerminalStyle($color);
        $this->write($style->label($message) . ' ' . $style->argument('[Y/n]') . ' ');
        $answer = strtolower(trim($this->read()));

        if (! in_array($answer, ['', 'y', 'yes'], true)) {
            throw new InvalidUsageException('Skill command cancelled.');
        }
    }

    public function assertInteractive(): void
    {
        if (! $this->isInteractive()) {
            throw new InvalidUsageException('Mutating skill commands require --yes or --all in non-interactive mode.');
        }
    }

    private function isInteractive(): bool
    {
        if ($this->interactive instanceof Closure) {
            return ($this->interactive)() === true;
        }

        return function_exists('stream_isatty') && stream_isatty(STDIN);
    }

    private function read(): string
    {
        if ($this->reader instanceof Closure) {
            $value = ($this->reader)();

            return is_string($value) ? $value : '';
        }

        $value = fgets(STDIN);

        return is_string($value) ? $value : '';
    }

    private function write(string $message): void
    {
        if ($this->writer instanceof Closure) {
            ($this->writer)($message);

            return;
        }

        fwrite(STDERR, $message);
        fflush(STDERR);
    }
}
