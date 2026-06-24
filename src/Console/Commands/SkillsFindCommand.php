<?php

declare(strict_types=1);

namespace Sift\Console\Commands;

use Closure;
use Sift\Console\CommandRoute;
use Sift\Console\InteractivePrompt;
use Sift\Console\InvalidUsageException;
use Sift\Skills\SkillCatalog;

final readonly class SkillsFindCommand implements CommandHandler
{
    /**
     * @param null|Closure(): bool $interactive
     */
    public function __construct(
        private SkillCatalog $catalog = new SkillCatalog(),
        private SkillsAddCommand $addCommand = new SkillsAddCommand(),
        private InteractivePrompt $interactivePrompt = new InteractivePrompt(),
        private ?Closure $interactive = null,
    ) {}

    public function handle(CommandRoute $route, string $cwd): array
    {
        $query = trim(implode(' ', $route->arguments()));
        $owner = $this->owner($route);

        if ($query === '') {
            if (! $this->shouldPrompt()) {
                return $this->emptyQueryPayload($owner);
            }

            $selected = $this->interactivePrompt->searchSkills($this->catalog, $owner);

            if ($selected === null) {
                return $this->cancelledPayload($owner);
            }

            $source = is_string($selected['source'] ?? null) ? $selected['source'] : '';
            $name = is_string($selected['name'] ?? null) ? $selected['name'] : '';

            if ($source === '' || $name === '') {
                return $this->cancelledPayload($owner);
            }

            return $this->addCommand->handle(
                new CommandRoute('skills.add', [$source . '@' . $name], globalOptions: $route->globalOptions()),
                $cwd,
            );
        }

        $items = $this->catalog->search($query, $owner);
        $meta = [
            'subcommand' => 'skills find',
            'query' => $query,
        ];

        if ($owner !== null) {
            $meta['owner'] = $owner;
        }

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'total' => count($items),
            ],
            'items' => $items,
            'artifacts' => [],
            'extra' => [],
            'meta' => $meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyQueryPayload(?string $owner): array
    {
        $meta = [
            'subcommand' => 'skills find',
            'query' => '',
            'mode' => 'agent_tip',
        ];

        if ($owner !== null) {
            $meta['owner'] = $owner;
        }

        return [
            'tool' => 'sift',
            'status' => 'passed',
            'summary' => [
                'total' => 0,
            ],
            'items' => [],
            'artifacts' => [],
            'extra' => [],
            'meta' => $meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cancelledPayload(?string $owner): array
    {
        $payload = $this->emptyQueryPayload($owner);
        $meta = $payload['meta'];

        if (is_array($meta) && ! array_is_list($meta)) {
            $meta['mode'] = 'cancelled';
            $payload['meta'] = $meta;
        }

        return $payload;
    }

    private function owner(CommandRoute $route): ?string
    {
        $owner = $route->options()['owner'] ?? null;

        if ($owner === null) {
            return null;
        }

        if (! is_string($owner)) {
            throw new InvalidUsageException('--owner must be a valid GitHub owner.');
        }

        $owner = strtolower(trim($owner));

        if ($owner === '') {
            throw new InvalidUsageException('--owner requires a GitHub owner.');
        }

        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38})$/', $owner) !== 1) {
            throw new InvalidUsageException('--owner must be a valid GitHub owner.');
        }

        return $owner;
    }

    private function shouldPrompt(): bool
    {
        return $this->isInteractive() && ! $this->isRunningInAgent();
    }

    private function isInteractive(): bool
    {
        if ($this->interactive instanceof Closure) {
            return ($this->interactive)() === true;
        }

        return function_exists('stream_isatty') && stream_isatty(STDIN);
    }

    private function isRunningInAgent(): bool
    {
        foreach (['CODEX_CI', 'CODEX_SHELL', 'CLAUDECODE', 'CLAUDE_CODE', 'GITHUB_COPILOT_AGENT'] as $name) {
            $value = getenv($name);

            if (is_string($value) && ! in_array(strtolower($value), ['', '0', 'false', 'no'], true)) {
                return true;
            }
        }

        return false;
    }

}
