<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;

final readonly class InstructionTargetRegistry
{
    public function resolve(string $target): InstructionTarget
    {
        $normalized = $this->normalize($target);

        if ($normalized === 'codex') {
            return new CodexSkillTarget();
        }

        if ($normalized === 'cursor') {
            return new CursorRuleTarget();
        }

        if ($normalized === 'windsurf') {
            return new WindsurfRuleTarget();
        }

        foreach ($this->descriptors() as $descriptor) {
            if ($descriptor->matches($normalized)) {
                return new InstructionFileTarget($descriptor->name(), $descriptor->relativePath());
            }
        }

        if (in_array($normalized, $this->recognizedReadOnlyNames(), true)) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::UnsupportedTarget,
                message: sprintf('Skill target "%s" is recognized but is not write-capable yet.', $target),
                hint: 'Use a stable write-capable target or wait until this target format is documented.',
                context: ['target' => $target, 'recognized' => true],
            );
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
    public function writeCapableNames(): array
    {
        return [
            'codex',
            'cursor',
            'windsurf',
            ...array_map(
                static fn(InstructionTargetDescriptor $descriptor): string => $descriptor->name(),
                $this->descriptors(),
            ),
        ];
    }

    private function normalize(string $target): string
    {
        return strtolower(trim($target));
    }

    /**
     * @return list<InstructionTargetDescriptor>
     */
    private function descriptors(): array
    {
        return [
            new InstructionTargetDescriptor('generic', 'AGENTS.md'),
            new InstructionTargetDescriptor('claude-code', 'CLAUDE.md', ['claude']),
            new InstructionTargetDescriptor('github-copilot', '.github/copilot-instructions.md', [
                'copilot',
                'vscode',
                'vs-code',
                'visual-studio-code',
            ]),
            new InstructionTargetDescriptor('gemini', 'GEMINI.md'),
        ];
    }

    /**
     * @return list<string>
     */
    private function recognizedReadOnlyNames(): array
    {
        return [
            'antigravity',
            'opencode',
        ];
    }
}
