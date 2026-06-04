<?php

declare(strict_types=1);

namespace Sift\Execution;

final readonly class Tty
{
    /**
     * @param resource|null $stream
     */
    public function __construct(
        private mixed $stream = null,
    ) {}

    public function isInteractive(): bool
    {
        $stream = $this->stream;

        if ($stream === null) {
            $stream = defined('STDERR') ? STDERR : null;
        }

        return is_resource($stream) && function_exists('stream_isatty') && stream_isatty($stream);
    }
}
