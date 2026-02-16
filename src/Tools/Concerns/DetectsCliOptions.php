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

    /**
     * @param  list<string>  $arguments
     */
    private function optionValue(array $arguments, string $option): ?string
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === $option) {
                $value = $arguments[$index + 1] ?? null;

                if (! is_string($value) || str_starts_with($value, '--')) {
                    return null;
                }

                return $value;
            }

            if (str_starts_with($argument, $option.'=')) {
                return substr($argument, strlen($option) + 1);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function floatOptionValue(array $arguments, string $option): ?float
    {
        $value = $this->optionValue($arguments, $option);

        if (! is_string($value) || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
