<?php

declare(strict_types=1);

namespace Sift\Tools\GrumPhp;

use Sift\Core\ItemType;

final readonly class GrumPhpParser
{
    public function parse(string $stdout, string $stderr): GrumPhpReport
    {
        $output = $this->withoutAnsi(trim(implode(PHP_EOL, array_filter(
            [$stdout, $stderr],
            static fn(string $value): bool => trim($value) !== '',
        ))));
        $details = $this->details($output);
        $tasks = $this->tasks($output, $details);
        $failedTasks = array_filter($tasks, static fn(array $task): bool => $task['failed']);
        $items = [];

        foreach ($failedTasks as $task) {
            $name = $task['name'];
            $message = trim($details[$name] ?? '');

            $items[] = [
                'type' => ItemType::Error->value,
                'task' => $name,
                'message' => $message === '' ? sprintf('GrumPHP task "%s" failed.', $name) : $message,
            ];
        }

        $failed = count($failedTasks);
        $total = count($tasks);

        return new GrumPhpReport(
            summary: ['tasks' => $total, 'passed' => $total - $failed, 'failed' => $failed],
            items: $items,
            failed: $failed,
        );
    }

    /**
     * @param array<string, string> $details
     * @return list<array{name: string, failed: bool}>
     */
    private function tasks(string $output, array $details): array
    {
        preg_match_all(
            '/^Running task\s+\d+\/\d+:\s*(?<name>[^.\r\n]+)\.{3}\s*(?<status>[^\r\n]*)$/miu',
            $output,
            $matches,
            PREG_SET_ORDER,
        );
        $tasks = [];

        foreach ($matches as $match) {
            $name = trim($match['name']);

            if ($name === '') {
                continue;
            }

            $status = trim($match['status']);
            $tasks[$name] = [
                'name' => $name,
                'failed' => isset($details[$name]) || preg_match('/(?:\x{2718}|\x{274C}|\bfailed\b|\berror\b)/iu', $status) === 1,
            ];
        }

        foreach (array_keys($details) as $name) {
            $tasks[$name] ??= ['name' => $name, 'failed' => true];
        }

        return array_values($tasks);
    }

    /**
     * @return array<string, string>
     */
    private function details(string $output): array
    {
        preg_match_all(
            '/^(?<name>[A-Za-z0-9_.:-]+)\R=+\R(?<message>.*?)(?=^[A-Za-z0-9_.:-]+\R=+\R|\z)/ms',
            $output,
            $matches,
            PREG_SET_ORDER,
        );
        $details = [];

        foreach ($matches as $match) {
            $name = trim($match['name']);
            $message = trim($match['message']);

            if ($name !== '' && $message !== '') {
                $details[$name] = $message;
            }
        }

        return $details;
    }

    private function withoutAnsi(string $output): string
    {
        return preg_replace('/\x1B(?:[@-Z\\-_]|\[[0-?]*[ -\/]*[@-~])/', '', $output) ?? $output;
    }
}
