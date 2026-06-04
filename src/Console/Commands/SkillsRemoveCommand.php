<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Console\ConfirmationPrompt;
use Sift\Console\InvalidUsageException;
use Sift\Skills\SkillTargetLock;
use Sift\Skills\Targets\InstructionTargetRegistry;

final readonly class SkillsRemoveCommand implements CommandHandler
{
    public function __construct(
        private InstructionTargetRegistry $targetRegistry = new InstructionTargetRegistry(),
        private SkillTargetLock $targetLock = new SkillTargetLock(),
        private ConfirmationPrompt $confirmationPrompt = new ConfirmationPrompt(),
    ) {}

    public function handle(CommandRoute $route, string $cwd): array
    {
        $skillName = $this->skillName($route);
        $targets = $this->targets($route);
        $this->assertConfirmed($route, sprintf('Remove skill %s from target(s) %s?', $skillName, implode(', ', $targets)));
        $items = $this->targetLock->synchronized($cwd, $targets, function () use ($cwd, $skillName, $targets): array {
            $items = [];

            foreach ($targets as $target) {
                $items[] = $this->targetRegistry->resolve($target)->remove($cwd, $skillName)->toItem();
            }

            return $items;
        });

        $removed = count(array_filter($items, static fn(array $item): bool => ($item['action'] ?? null) === 'removed'));

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'removed' => $removed,
            ],
            'items' => $items,
            'artifacts' => [],
            'extra' => [],
            'meta' => [
                'subcommand' => 'skills remove',
                'targets' => $targets,
            ],
        ];
    }

    private function assertConfirmed(CommandRoute $route, string $message): void
    {
        if (($route->options()['yes'] ?? false) === true || ($route->options()['all'] ?? false) === true) {
            return;
        }

        $this->confirmationPrompt->confirm($message);
    }

    private function skillName(CommandRoute $route): string
    {
        $argument = $route->arguments()[0] ?? null;
        $option = $route->options()['skill'] ?? null;
        $optionValues = is_array($option) ? $option : [$option];
        $optionName = count($optionValues) === 1 && is_string($optionValues[0]) ? $optionValues[0] : null;
        $name = is_string($argument) ? $argument : $optionName;

        if (! is_string($name) || preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $name) !== 1) {
            throw new InvalidUsageException('skills remove requires a skill name.');
        }

        return $name;
    }

    /**
     * @return list<string>
     */
    private function targets(CommandRoute $route): array
    {
        $agent = $route->options()['agent'] ?? null;

        if (! is_string($agent) || trim($agent) === '' || in_array(trim($agent), ['*', 'all'], true)) {
            return $this->targetRegistry->writeCapableNames();
        }

        $targets = [];

        foreach (explode(',', $agent) as $target) {
            $target = trim($target);

            if ($target !== '') {
                $targets[] = $target;
            }
        }

        return array_values(array_unique($targets));
    }
}
