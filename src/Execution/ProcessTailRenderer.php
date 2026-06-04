<?php

declare(strict_types=1);

namespace Sift\Execution;

use Sift\Core\PreparedCommand;
use Sift\History\SecretRedactor;

final readonly class ProcessTailRenderer
{
    public function __construct(
        private Tty $tty = new Tty(),
        private SecretRedactor $redactor = new SecretRedactor(),
    ) {}

    public function render(PreparedCommand $command): string
    {
        $payload = [
            'tool' => $command->tool(),
            'type' => 'process',
            'tty' => $this->tty->isInteractive(),
            'cwd' => $command->cwd(),
            'command' => $command->displayCommand(),
        ];

        return json_encode($this->redactor->redactPayload($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}
