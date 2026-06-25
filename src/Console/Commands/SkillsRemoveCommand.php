<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Sift\Console\CommandRoute;
use Sift\Console\ConfirmationPrompt;
use Sift\Console\InteractivePrompt;
use Sift\Console\InvalidUsageException;
use Sift\Skills\SkillManagedMetadata;
use Sift\Skills\SkillService;
use Sift\Skills\SkillTargetLock;
use Sift\Skills\Targets\InstructionTargetRegistry;

final readonly class SkillsRemoveCommand implements CommandHandler
{
    public function __construct(
        private SkillService $skillService = new SkillService(),
        private InstructionTargetRegistry $targetRegistry = new InstructionTargetRegistry(),
        private SkillTargetLock $targetLock = new SkillTargetLock(),
        private ConfirmationPrompt $confirmationPrompt = new ConfirmationPrompt(),
        private InteractivePrompt $interactivePrompt = new InteractivePrompt(),
    ) {}

    public function handle(CommandRoute $route, string $cwd): array
    {
        $global = $this->optionBool($route, 'global');
        $targets = $this->targets($route, $global);
        $usesInteractivePrompt = $this->usesInteractivePrompt($route);
        $skillNames = $this->skillNames($route, $cwd, $targets, $global);
        $this->assertConfirmed(
            $route,
            sprintf('Remove skill(s) %s from target(s) %s?', implode(', ', $skillNames), implode(', ', $targets)),
            $usesInteractivePrompt,
        );
        $items = $this->targetLock->synchronized($cwd, $targets, function () use ($cwd, $skillNames, $targets, $global): array {
            $items = [];

            foreach ($skillNames as $skillName) {
                foreach ($targets as $target) {
                    $items[] = $this->targetRegistry->resolve($target, $global)->remove($cwd, $skillName)->toItem();
                }
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
                'skills' => $skillNames,
                'global' => $global,
            ],
        ];
    }

    private function assertConfirmed(CommandRoute $route, string $message, bool $usesInteractivePrompt): void
    {
        if (($route->options()['yes'] ?? false) === true || ($route->options()['all'] ?? false) === true) {
            return;
        }

        if ($usesInteractivePrompt) {
            if (! $this->interactivePrompt->confirm($message, $this->colorEnabled($route))) {
                throw new InvalidUsageException('Skill command cancelled.');
            }

            return;
        }

        $this->confirmationPrompt->confirm($message, $this->colorEnabled($route));
    }

    /**
     * @param list<string> $targets
     *
     * @return list<string>
     */
    private function skillNames(CommandRoute $route, string $cwd, array $targets, bool $global): array
    {
        $requested = $this->requestedSkillNames($route);

        if ($this->optionBool($route, 'all') || in_array('*', $requested, true)) {
            return $this->installedSkillNames($cwd, $targets, $global);
        }

        if ($requested !== []) {
            return $this->validateSkillNames($requested);
        }

        $installed = $this->skillService->inventory($cwd, $targets, $global);

        if ($installed === []) {
            throw new InvalidUsageException('No installed skills found.');
        }

        $selected = $this->interactivePrompt->multiselect(
            'Select skills to remove',
            array_map(
                static fn(SkillManagedMetadata $metadata): array => [
                    'value' => $metadata->name(),
                    'label' => $metadata->name(),
                    'hint' => 'Agents: ' . implode(', ', $metadata->targets()),
                ],
                $installed,
            ),
            $this->colorEnabled($route),
        );

        if ($selected === null || $selected === []) {
            throw new InvalidUsageException('Skill command cancelled.');
        }

        return $selected;
    }

    /**
     * @return list<string>
     */
    private function requestedSkillNames(CommandRoute $route): array
    {
        $names = [];

        foreach ($route->arguments() as $argument) {
            foreach (explode(',', $argument) as $name) {
                $name = trim($name);

                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        $option = $route->options()['skill'] ?? null;
        $optionValues = is_array($option) ? $option : [$option];

        foreach ($optionValues as $optionValue) {
            if (! is_string($optionValue)) {
                continue;
            }

            foreach (explode(',', $optionValue) as $name) {
                $name = trim($name);

                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    private function usesInteractivePrompt(CommandRoute $route): bool
    {
        return ! $this->optionBool($route, 'all') && $this->requestedSkillNames($route) === [];
    }

    /**
     * @param list<string> $targets
     *
     * @return list<string>
     */
    private function installedSkillNames(string $cwd, array $targets, bool $global): array
    {
        $names = array_map(
            static fn(SkillManagedMetadata $metadata): string => $metadata->name(),
            $this->skillService->inventory($cwd, $targets, $global),
        );

        if ($names === []) {
            throw new InvalidUsageException('No installed skills found.');
        }

        return array_values(array_unique($names));
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function validateSkillNames(array $names): array
    {
        foreach ($names as $name) {
            if (preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $name) !== 1) {
                throw new InvalidUsageException('skills remove requires a skill name.');
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function targets(CommandRoute $route, bool $global): array
    {
        $agents = $this->stringOptionValues($route, 'agent');

        if ($agents === []) {
            return $this->targetRegistry->writeCapableNames($global);
        }

        $targets = [];

        foreach ($agents as $target) {
            $target = trim($target);

            if ($target === '' || in_array($target, ['*', 'all'], true)) {
                return $this->targetRegistry->writeCapableNames($global);
            }

            $targets[] = $target;
        }

        return array_values(array_unique($targets));
    }

    /**
     * @return list<string>
     */
    private function stringOptionValues(CommandRoute $route, string $name): array
    {
        $value = $route->options()[$name] ?? null;
        $values = is_array($value) ? $value : [$value];
        $strings = [];

        foreach ($values as $item) {
            if (! is_string($item)) {
                continue;
            }

            foreach (explode(',', $item) as $string) {
                if (trim($string) !== '') {
                    $strings[] = trim($string);
                }
            }
        }

        return $strings;
    }

    private function optionBool(CommandRoute $route, string $name): bool
    {
        return ($route->options()[$name] ?? false) === true;
    }

    private function colorEnabled(CommandRoute $route): bool
    {
        return ($route->globalOptions()['no-color'] ?? false) !== true
            && ($route->options()['no-color'] ?? false) !== true;
    }
}
