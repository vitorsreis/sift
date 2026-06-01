<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Console\InvalidUsageException;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;

final readonly class HistoryViewCommand extends AbstractHistoryCommand
{
    /**
     * @var list<string>
     */
    private const array SECTIONS = ['summary', 'items', 'meta', 'artifacts', 'extra'];

    public function handle(CommandRoute $route, string $cwd): array
    {
        $arguments = $route->arguments();
        $runId = $this->runId($arguments[0] ?? '');
        $section = $arguments[1] ?? null;

        if (count($arguments) > 2) {
            throw new InvalidUsageException('History view accepts only a run id and optional section.');
        }

        if ($section !== null && ! in_array($section, self::SECTIONS, true)) {
            throw new InvalidUsageException(sprintf('Unsupported history section "%s".', $section));
        }

        $record = $this->store($route, $cwd)->read($runId);

        if ($record === null) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::RunNotFound,
                message: 'History run was not found.',
                context: ['run_id' => $runId],
            );
        }

        $payload = $this->objectValue($record['payload'] ?? []);

        if ($section === null) {
            return [
                'tool' => $this->stringValue($payload['tool'] ?? null, 'sift'),
                'status' => $this->stringValue($payload['status'] ?? null, 'passed'),
                'summary' => $this->objectValue($payload['summary'] ?? []),
                'items' => $this->listValue($payload['items'] ?? []),
                'artifacts' => $this->listValue($payload['artifacts'] ?? []),
                'extra' => $this->objectValue($payload['extra'] ?? []),
                'meta' => $this->objectValue($payload['meta'] ?? []),
            ];
        }

        return $this->sectionPayload($runId, $section, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function sectionPayload(string $runId, string $section, array $payload): array
    {
        return match ($section) {
            'summary' => [
                'tool' => 'sift',
                'status' => 'passed',
                'summary' => $this->objectValue($payload['summary'] ?? []),
                'items' => [],
                'artifacts' => [],
                'extra' => [],
                'meta' => [],
            ],
            'items' => $this->listSectionPayload($runId, 'items', $this->listValue($payload['items'] ?? [])),
            'artifacts' => $this->listSectionPayload($runId, 'artifacts', $this->listValue($payload['artifacts'] ?? [])),
            'meta' => $this->objectSectionPayload('meta', $this->objectValue($payload['meta'] ?? [])),
            'extra' => $this->objectSectionPayload('extra', $this->objectValue($payload['extra'] ?? [])),
            default => throw new InvalidUsageException(sprintf('Unsupported history section "%s".', $section)),
        };
    }

    /**
     * @param list<mixed> $items
     *
     * @return array<string, mixed>
     */
    private function listSectionPayload(string $runId, string $section, array $items): array
    {
        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'run_id' => $runId,
                $section => count($items),
            ],
            'items' => $section === 'items' ? $items : [],
            'artifacts' => $section === 'artifacts' ? $items : [],
            'extra' => [],
            'meta' => [],
        ];
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function objectSectionPayload(string $section, array $values): array
    {
        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [],
            'items' => [],
            'artifacts' => [],
            'extra' => $section === 'extra' ? $values : [],
            'meta' => $section === 'meta' ? $values : [],
        ];
    }

    private function stringValue(mixed $value, string $fallback): string
    {
        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
