<?php

declare(strict_types=1);

use Sift\Core\PreparedCommand;
use Sift\Execution\ProcessTailRenderer;
use Sift\Execution\Tty;

it('renders redacted process events with tty state', function (): void {
    $stream = fopen('php://temp', 'r+');

    if ($stream === false) {
        throw new RuntimeException('Could not open temp stream.');
    }

    $payload = processTailRendererPayload((new ProcessTailRenderer(new Tty($stream)))->render(new PreparedCommand(
        tool: 'composer',
        binary: 'composer',
        arguments: ['show', '--token=secret-value'],
        cwd: getcwd() ?: '.',
    )));

    expect($payload)->toMatchArray([
        'tool' => 'composer',
        'type' => 'process',
        'tty' => false,
    ]);
    expect(processTailRendererList($payload, 'command'))->toContain('--token=[REDACTED]');
});

/**
 * @return array<string, mixed>
 */
function processTailRendererPayload(string $json): array
{
    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($decoded) || array_is_list($decoded)) {
        throw new RuntimeException('Expected process event payload object.');
    }

    $payload = [];

    foreach ($decoded as $key => $value) {
        if (! is_string($key)) {
            throw new RuntimeException('Expected process event payload object with string keys.');
        }

        $payload[$key] = $value;
    }

    return $payload;
}

/**
 * @param array<string, mixed> $payload
 * @return list<mixed>
 */
function processTailRendererList(array $payload, string $key): array
{
    $value = $payload[$key] ?? null;

    if (! is_array($value) || ! array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected process event field "%s" to be a list.', $key));
    }

    return $value;
}
