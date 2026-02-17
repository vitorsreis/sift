<?php

declare(strict_types=1);

namespace Sift\Tools\Concerns;

use Sift\Exceptions\UserFacingException;

trait EnsuresCommandOptionValues
{
    /**
     * @param  list<string>  $arguments
     * @return array{0: list<string>, 1: string, 2: bool}
     */
    private function ensureOptionValue(array $arguments, string $option, string $defaultValue): array
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === $option && isset($arguments[$index + 1])) {
                return [$arguments, $arguments[$index + 1], false];
            }

            if (str_starts_with($argument, $option.'=')) {
                return [$arguments, substr($argument, strlen($option) + 1), false];
            }
        }

        $arguments[] = $option;
        $arguments[] = $defaultValue;

        return [$arguments, $defaultValue, true];
    }

    private function tempFile(string $prefix, string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw UserFacingException::parseFailure($this->name(), 'Unable to allocate temporary file.');
        }

        $target = $path.$extension;
        @rename($path, $target);

        return $target;
    }
}
