<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

use Closure;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\Path;

final readonly class InstructionTargetRegistry
{
    private const int SHARED_TARGET_HINT_LIMIT = 5;

    private const array SHARED_TARGET_PRIORITY = [
        'codex',
        'cursor',
        'github-copilot',
        'gemini-cli',
        'opencode',
        'amp',
        'replit',
        'universal',
    ];

    public function resolve(string $target, bool $global = false): InstructionTarget
    {
        $normalized = $this->normalize($target);
        $eveSubagent = $this->eveSubagent($normalized);

        if ($eveSubagent !== null) {
            if ($global) {
                throw UserFacingException::withContext(
                    errorCode: ErrorCode::UnsupportedTarget,
                    message: sprintf('Skill target "%s" does not support global installs.', $target),
                    context: ['target' => $target, 'global' => true],
                );
            }

            return new SkillDirectoryTarget(
                name: $eveSubagent['name'],
                projectRelativeDirectory: $eveSubagent['project'],
                global: false,
            );
        }

        foreach ($this->directoryTargets() as $name => $definition) {
            if ($this->matches($normalized, $name, $definition['aliases'] ?? [])) {
                return new SkillDirectoryTarget(
                    name: $name,
                    projectRelativeDirectory: $definition['project'],
                    globalDirectoryResolver: $this->globalResolver($name, $definition['global'] ?? null),
                    global: $global,
                );
            }
        }

        foreach ($this->descriptors() as $descriptor) {
            if ($descriptor->matches($normalized)) {
                if ($global) {
                    throw UserFacingException::withContext(
                        errorCode: ErrorCode::UnsupportedTarget,
                        message: sprintf('Skill target "%s" does not support global installs.', $target),
                        context: ['target' => $target, 'global' => true],
                    );
                }

                return new InstructionFileTarget($descriptor->name(), $descriptor->relativePath());
            }
        }

        throw UserFacingException::withContext(
            errorCode: ErrorCode::UnsupportedTarget,
            message: sprintf('Skill target "%s" is not supported yet.', $target),
            context: ['target' => $target, 'recognized' => false],
        );
    }

    /**
     * @return list<string>
     */
    public function writeCapableNames(bool $global = false): array
    {
        $names = [];

        foreach ($this->directoryTargets() as $name => $definition) {
            if ($global && ($definition['global'] ?? null) === null) {
                continue;
            }

            $names[] = $name;
        }

        if (! $global) {
            foreach ($this->descriptors() as $descriptor) {
                $names[] = $descriptor->name();
            }
        }

        return $names;
    }

    /**
     * @return list<array{value: string, label: string, hint: string, selected: bool}>
     */
    public function agentChoices(string $cwd, bool $global = false): array
    {
        $directoryChoices = [];

        foreach ($this->directoryTargets() as $name => $definition) {
            if ($global && ($definition['global'] ?? null) === null) {
                continue;
            }

            $hint = $global
                ? $this->globalDirectory($name, $definition['global'] ?? null)
                : $definition['project'];
            $path = $global ? $hint : Path::join($cwd, $definition['project']);
            $selected = is_dir($path);
            $directoryChoices[] = [
                'value' => $name,
                'label' => $name,
                'hint' => $hint,
                'selected' => $selected,
            ];
        }

        $choices = $this->groupSharedDirectoryChoices($directoryChoices);

        if (! $global) {
            foreach ($this->descriptors() as $descriptor) {
                $path = Path::join($cwd, $descriptor->relativePath());
                $selected = is_file($path);
                $choices[] = [
                    'value' => $descriptor->name(),
                    'label' => $descriptor->name(),
                    'hint' => $descriptor->relativePath(),
                    'selected' => $selected,
                ];
            }
        }

        $hasSelected = array_filter(
            $choices,
            static fn(array $choice): bool => $choice['selected'] === true,
        ) !== [];

        if (! $hasSelected) {
            foreach ($choices as $index => $choice) {
                if ($choice['value'] === 'codex') {
                    $choices[$index]['selected'] = true;

                    break;
                }
            }
        }

        return $choices;
    }

    /**
     * @param list<array{value: string, label: string, hint: string, selected: bool}> $choices
     *
     * @return list<array{value: string, label: string, hint: string, selected: bool}>
     */
    private function groupSharedDirectoryChoices(array $choices): array
    {
        $groups = [];
        $order = [];

        foreach ($choices as $choice) {
            $key = $choice['hint'];

            if (! array_key_exists($key, $groups)) {
                $groups[$key] = [];
                $order[] = $key;
            }

            $groups[$key][] = $choice;
        }

        $grouped = [];

        foreach ($order as $key) {
            $items = $groups[$key];

            if (count($items) === 1) {
                $grouped[] = $items[0];

                continue;
            }

            $representative = $this->representativeChoice($items);
            $names = array_column($items, 'value');

            $grouped[] = [
                'value' => $representative['value'],
                'label' => sprintf('%s (+%d)', $representative['value'], count($items) - 1),
                'hint' => $this->sharedDirectoryHint($representative['hint'], $names, $representative['value']),
                'selected' => array_filter(
                    $items,
                    static fn(array $choice): bool => $choice['selected'] === true,
                ) !== [],
            ];
        }

        return $grouped;
    }

    /**
     * @param non-empty-list<array{value: string, label: string, hint: string, selected: bool}> $choices
     *
     * @return array{value: string, label: string, hint: string, selected: bool}
     */
    private function representativeChoice(array $choices): array
    {
        $names = $this->prioritizedTargetNames(array_column($choices, 'value'));

        foreach ($names as $name) {
            foreach ($choices as $choice) {
                if ($choice['value'] === $name) {
                    return $choice;
                }
            }
        }

        return $choices[0];
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function prioritizedTargetNames(array $names): array
    {
        $priority = array_flip(self::SHARED_TARGET_PRIORITY);

        usort($names, static function (string $left, string $right) use ($priority): int {
            $leftRank = $priority[$left] ?? PHP_INT_MAX;
            $rightRank = $priority[$right] ?? PHP_INT_MAX;

            if ($leftRank === $rightRank) {
                return $left <=> $right;
            }

            return $leftRank <=> $rightRank;
        });

        return $names;
    }

    /**
     * @param list<string> $names
     */
    private function sharedDirectoryHint(string $directory, array $names, string $representative): string
    {
        $sharedNames = array_values(array_filter(
            $this->prioritizedTargetNames($names),
            static fn(string $name): bool => $name !== $representative,
        ));
        $preview = array_slice($sharedNames, 0, self::SHARED_TARGET_HINT_LIMIT);
        $remaining = count($sharedNames) - count($preview);

        if ($remaining > 0) {
            $preview[] = '+' . $remaining;
        }

        return sprintf('%s (shared: %s)', $directory, implode(', ', $preview));
    }

    /**
     * @param list<string> $targets
     */
    public function anySupportsGlobal(array $targets): bool
    {
        foreach ($targets as $target) {
            if ($this->supportsGlobal($target)) {
                return true;
            }
        }

        return false;
    }

    public function supportsGlobal(string $target): bool
    {
        $normalized = $this->normalize($target);

        if ($this->eveSubagent($normalized) !== null) {
            return false;
        }

        foreach ($this->directoryTargets() as $name => $definition) {
            if ($this->matches($normalized, $name, $definition['aliases'] ?? [])) {
                return ($definition['global'] ?? null) !== null;
            }
        }

        foreach ($this->descriptors() as $descriptor) {
            if ($descriptor->matches($normalized)) {
                return false;
            }
        }

        throw UserFacingException::withContext(
            errorCode: ErrorCode::UnsupportedTarget,
            message: sprintf('Skill target "%s" is not supported yet.', $target),
            context: ['target' => $target, 'recognized' => false],
        );
    }

    private function normalize(string $target): string
    {
        return strtolower(trim($target));
    }

    /**
     * @return null|array{name: string, project: string}
     */
    private function eveSubagent(string $target): ?array
    {
        if (preg_match('/^eve[:\/](?P<name>[a-z0-9][a-z0-9-]{0,63})$/', $target, $matches) !== 1) {
            return null;
        }

        $name = $matches['name'];

        return [
            'name' => 'eve:' . $name,
            'project' => 'agent/subagents/' . $name . '/skills',
        ];
    }

    /**
     * @param list<string> $aliases
     */
    private function matches(string $target, string $name, array $aliases): bool
    {
        return $target === $name || in_array($target, $aliases, true);
    }

    /**
     * @return array<string, array{project: string, global?: string|null, aliases?: list<string>}>
     */
    private function directoryTargets(): array
    {
        return [
            'aider-desk' => ['project' => '.aider-desk/skills', 'global' => '.aider-desk/skills'],
            'amp' => ['project' => '.agents/skills', 'global' => '.config/agents/skills'],
            'replit' => ['project' => '.agents/skills', 'global' => '.config/agents/skills'],
            'universal' => ['project' => '.agents/skills', 'global' => '.config/agents/skills'],
            'antigravity' => ['project' => '.agents/skills', 'global' => '.gemini/antigravity/skills'],
            'antigravity-cli' => ['project' => '.agents/skills', 'global' => '.gemini/antigravity-cli/skills'],
            'astrbot' => ['project' => 'data/skills', 'global' => '.astrbot/data/skills'],
            'autohand-code' => ['project' => '.autohand/skills', 'global' => '.autohand/skills'],
            'augment' => ['project' => '.augment/skills', 'global' => '.augment/skills'],
            'bob' => ['project' => '.bob/skills', 'global' => '.bob/skills'],
            'claude-code' => ['project' => '.claude/skills', 'global' => '.claude/skills', 'aliases' => ['claude']],
            'openclaw' => ['project' => 'skills', 'global' => '.openclaw/skills'],
            'cline' => ['project' => '.agents/skills', 'global' => '.agents/skills'],
            'dexto' => ['project' => '.agents/skills', 'global' => '.agents/skills'],
            'kimi-code-cli' => ['project' => '.agents/skills', 'global' => '.agents/skills'],
            'loaf' => ['project' => '.agents/skills', 'global' => '.agents/skills'],
            'warp' => ['project' => '.agents/skills', 'global' => '.agents/skills'],
            'zed' => ['project' => '.agents/skills', 'global' => '.agents/skills'],
            'codearts-agent' => ['project' => '.codeartsdoer/skills', 'global' => '.codeartsdoer/skills'],
            'codebuddy' => ['project' => '.codebuddy/skills', 'global' => '.codebuddy/skills'],
            'codemaker' => ['project' => '.codemaker/skills', 'global' => '.codemaker/skills'],
            'codestudio' => ['project' => '.codestudio/skills', 'global' => '.codestudio/skills'],
            'codex' => ['project' => '.agents/skills', 'global' => '.codex/skills'],
            'command-code' => ['project' => '.commandcode/skills', 'global' => '.commandcode/skills'],
            'continue' => ['project' => '.continue/skills', 'global' => '.continue/skills'],
            'cortex' => ['project' => '.cortex/skills', 'global' => '.snowflake/cortex/skills'],
            'crush' => ['project' => '.crush/skills', 'global' => '.config/crush/skills'],
            'cursor' => ['project' => '.agents/skills', 'global' => '.cursor/skills'],
            'deepagents' => ['project' => '.agents/skills', 'global' => '.deepagents/agent/skills'],
            'devin' => ['project' => '.devin/skills', 'global' => '.config/devin/skills'],
            'droid' => ['project' => '.factory/skills', 'global' => '.factory/skills'],
            'eve' => ['project' => 'agent/skills', 'global' => null],
            'firebender' => ['project' => '.agents/skills', 'global' => '.firebender/skills'],
            'forgecode' => ['project' => '.forge/skills', 'global' => '.forge/skills'],
            'gemini-cli' => ['project' => '.agents/skills', 'global' => '.gemini/skills', 'aliases' => ['gemini']],
            'github-copilot' => ['project' => '.agents/skills', 'global' => '.copilot/skills', 'aliases' => ['copilot', 'vscode', 'vs-code', 'visual-studio-code']],
            'goose' => ['project' => '.goose/skills', 'global' => '.config/goose/skills'],
            'hermes-agent' => ['project' => '.hermes/skills', 'global' => '.hermes/skills'],
            'inference-sh' => ['project' => '.inferencesh/skills', 'global' => '.inferencesh/skills'],
            'jazz' => ['project' => '.jazz/skills', 'global' => '.jazz/skills'],
            'junie' => ['project' => '.junie/skills', 'global' => '.junie/skills'],
            'iflow-cli' => ['project' => '.iflow/skills', 'global' => '.iflow/skills'],
            'kilo' => ['project' => '.kilocode/skills', 'global' => '.kilocode/skills'],
            'kiro-cli' => ['project' => '.kiro/skills', 'global' => '.kiro/skills'],
            'kode' => ['project' => '.kode/skills', 'global' => '.kode/skills'],
            'lingma' => ['project' => '.lingma/skills', 'global' => '.lingma/skills'],
            'mcpjam' => ['project' => '.mcpjam/skills', 'global' => '.mcpjam/skills'],
            'mistral-vibe' => ['project' => '.vibe/skills', 'global' => '.vibe/skills'],
            'moxby' => ['project' => '.moxby/skills', 'global' => '.moxby/skills'],
            'mux' => ['project' => '.mux/skills', 'global' => '.mux/skills'],
            'opencode' => ['project' => '.agents/skills', 'global' => '.config/opencode/skills'],
            'openhands' => ['project' => '.openhands/skills', 'global' => '.openhands/skills'],
            'ona' => ['project' => '.ona/skills', 'global' => '.ona/skills'],
            'pi' => ['project' => '.pi/skills', 'global' => '.pi/agent/skills'],
            'qoder' => ['project' => '.qoder/skills', 'global' => '.qoder/skills'],
            'qoder-cn' => ['project' => '.qoder/skills', 'global' => '.qoder-cn/skills'],
            'qwen-code' => ['project' => '.qwen/skills', 'global' => '.qwen/skills'],
            'reasonix' => ['project' => '.reasonix/skills', 'global' => '.reasonix/skills'],
            'rovodev' => ['project' => '.rovodev/skills', 'global' => '.rovodev/skills'],
            'roo' => ['project' => '.roo/skills', 'global' => '.roo/skills', 'aliases' => ['roo-code']],
            'tabnine-cli' => ['project' => '.tabnine/agent/skills', 'global' => '.tabnine/agent/skills'],
            'terramind' => ['project' => '.terramind/skills', 'global' => '.terramind/skills'],
            'tinycloud' => ['project' => '.tinycloud/skills', 'global' => '.tinycloud/skills'],
            'trae' => ['project' => '.trae/skills', 'global' => '.trae/skills'],
            'trae-cn' => ['project' => '.trae/skills', 'global' => '.trae-cn/skills'],
            'windsurf' => ['project' => '.windsurf/skills', 'global' => '.codeium/windsurf/skills'],
            'zencoder' => ['project' => '.zencoder/skills', 'global' => '.zencoder/skills'],
            'zenflow' => ['project' => '.zencoder/skills', 'global' => '.zencoder/skills'],
            'neovate' => ['project' => '.neovate/skills', 'global' => '.neovate/skills'],
            'pochi' => ['project' => '.pochi/skills', 'global' => '.pochi/skills'],
            'promptscript' => ['project' => '.agents/skills', 'global' => null],
            'adal' => ['project' => '.adal/skills', 'global' => '.adal/skills'],
        ];
    }

    private function globalResolver(string $name, ?string $relativePath): ?Closure
    {
        if ($relativePath === null) {
            return null;
        }

        if ($name === 'codex') {
            return static fn(): string => Path::join((new CodexHomeResolver())->resolve(), 'skills');
        }

        if ($name === 'claude-code') {
            return fn(): string => Path::join($this->environmentOrHomePath('CLAUDE_CONFIG_DIR', '.claude'), 'skills');
        }

        return fn(): string => $this->homePath($relativePath);
    }

    private function globalDirectory(string $name, ?string $relativePath): string
    {
        $resolver = $this->globalResolver($name, $relativePath);

        if (! $resolver instanceof Closure) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::UnsupportedTarget,
                message: sprintf('Skill target "%s" does not support global installs.', $name),
                context: ['target' => $name, 'global' => true],
            );
        }

        $directory = $resolver();

        if (! is_string($directory)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: sprintf('Could not resolve global skill directory for target "%s".', $name),
                context: ['target' => $name],
            );
        }

        return Path::normalize($directory);
    }

    private function environmentOrHomePath(string $environmentKey, string $homeRelativePath): string
    {
        $value = getenv($environmentKey);

        if (is_string($value) && trim($value) !== '') {
            return Path::normalize($value);
        }

        return $this->homePath($homeRelativePath);
    }

    private function homePath(string $relativePath): string
    {
        return Path::join($this->home(), ltrim($relativePath, '/\\'));
    }

    private function home(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        if (! is_string($home) || trim($home) === '') {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: 'Unable to resolve the user home directory.',
            );
        }

        return Path::normalize($home);
    }

    /**
     * @return list<InstructionTargetDescriptor>
     */
    private function descriptors(): array
    {
        return [
            new InstructionTargetDescriptor('generic', 'AGENTS.md'),
        ];
    }
}
