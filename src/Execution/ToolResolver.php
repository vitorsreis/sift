<?php

declare(strict_types=1);

namespace Sift\Execution;

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\ToolDefinition;

final readonly class ToolResolver
{
    public function __construct(
        private ToolLocator $locator = new ToolLocator(),
    ) {}

    public function resolve(ToolDefinition $definition, ToolConfig $config, string $cwd): LocatedTool
    {
        if (! $config->enabled()) {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::ToolDisabled,
                message: sprintf('Tool "%s" is disabled.', $definition->name()),
                hint: 'Enable the tool in sift.json before running it.',
                context: ['tool' => $definition->name()],
            );
        }

        $candidates = $config->binary() === null
            ? $definition->binaryCandidates()
            : [$config->binary()];

        foreach ($candidates as $candidate) {
            try {
                return $this->locator->locate($definition->name(), $candidate, $cwd);
            } catch (UserFacingException $exception) {
                if ($exception->errorCode() !== ErrorCode::ToolNotFound) {
                    throw $exception;
                }
            }
        }

        throw UserFacingException::withContext(
            errorCode: ErrorCode::ToolNotFound,
            message: sprintf('Tool "%s" could not be located.', $definition->name()),
            hint: $definition->installHint(),
            context: [
                'tool' => $definition->name(),
                'candidates' => $candidates,
            ],
        );
    }
}
