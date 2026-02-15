<?php

declare(strict_types=1);

namespace Sift\Tools\Concerns;

trait DetectsCliOptions
{
    /**
     * @param  list<string>  $arguments
     */
    private function hasOption(array $arguments, string $option): bool
    {
        foreach ($arguments as $argument) {
            if ($argument === $option || str_starts_with($argument, $option.'=')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $arguments
     * @param  list<string>  $options
     */
    private function hasAnyOption(array $arguments, array $options): bool
    {
        foreach ($options as $option) {
            if ($this->hasOption($arguments, $option)) {
                return true;
            }
        }

        return false;
    }
}
