<?php

declare(strict_types=1);

namespace Sift\Skills\Targets;

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Filesystem\Path;

final readonly class CodexHomeResolver
{
    public function __construct(
        private ?string $override = null,
    ) {}

    public function resolve(): string
    {
        if (is_string($this->override) && trim($this->override) !== '') {
            return Path::normalize($this->override);
        }

        $codexHome = getenv('CODEX_HOME');

        if (is_string($codexHome) && trim($codexHome) !== '') {
            return Path::normalize($codexHome);
        }

        $home = getenv('HOME') ?: getenv('USERPROFILE');

        if (! is_string($home) || trim($home) === '') {
            throw UserFacingException::withContext(
                errorCode: ErrorCode::FilesystemError,
                message: 'Unable to resolve the Codex home directory.',
            );
        }

        return Path::join($home, '.codex');
    }
}
