<?php

declare(strict_types=1);

namespace Sift\Console;

use Sift\Config\ConfigDefaults;
use Sift\Config\OutputConfig;
use Sift\Config\SiftConfig;

/**
 * @phpstan-import-type ParsedOption from CliRequest
 */
final readonly class OutputPreferencesResolver
{
    public function __construct(
        private bool $runningInCi = false,
    ) {}

    public static function fromEnvironment(): self
    {
        $ci = getenv('CI');
        $runningInCi = is_string($ci) && ! in_array(strtolower($ci), ['', '0', 'false', 'no'], true);

        return new self($runningInCi);
    }

    public function defaults(?SiftConfig $config = null): OutputPreferences
    {
        $defaults = ConfigDefaults::output();
        $output = $config?->output();
        $size = $output instanceof OutputConfig ? OutputSize::from($output->size()) : OutputSize::from($defaults['size']);
        $format = $output instanceof OutputConfig ? OutputFormat::from($output->format()) : OutputFormat::from($defaults['format']);
        $pretty = $output instanceof OutputConfig ? $output->pretty() : ($this->runningInCi ? false : $defaults['pretty']);
        $showProcess = $output instanceof OutputConfig ? $output->showProcess() : $defaults['show_process'];
        $color = $output instanceof OutputConfig ? $output->colored() : $defaults['colored'];

        return new OutputPreferences(
            size: $size,
            pretty: $pretty,
            showProcess: $showProcess,
            debug: false,
            format: $format,
            color: $color,
        );
    }

    public function resolve(CommandRoute $route, ?SiftConfig $config = null): OutputPreferences
    {
        $preferences = $this->defaults($config);
        $preferences = $this->applyOptions($preferences, $route->globalOptions());

        return $this->applyOptions($preferences, $route->options());
    }

    /**
     * @param array<string, ParsedOption> $options
     */
    private function applyOptions(OutputPreferences $current, array $options): OutputPreferences
    {
        $size = $this->sizeFromOptions($options) ?? $current->size();
        $pretty = $this->boolFromOptions($options, 'pretty', 'no-pretty', $current->pretty());
        $showProcess = $this->boolFromOptions($options, 'show-process', 'no-show-process', $current->showProcess());
        $debug = $this->optionEnabled($options, 'debug') || $current->debug();
        $format = $this->formatFromOptions($options) ?? $current->format();
        $color = $this->colorFromOptions($options, $current->color());

        return new OutputPreferences($size, $pretty, $showProcess, $debug, $format, $color);
    }

    /**
     * @param array<string, ParsedOption> $options
     */
    private function colorFromOptions(array $options, bool $current): bool
    {
        return $this->optionEnabled($options, 'no-color') ? false : $current;
    }

    /**
     * @param array<string, ParsedOption> $options
     */
    private function formatFromOptions(array $options): ?OutputFormat
    {
        if ($this->optionEnabled($options, 'json') && $this->optionEnabled($options, 'raw')) {
            throw new InvalidUsageException('Options "--json" and "--raw" cannot be used together.');
        }

        if ($this->optionEnabled($options, 'json') && $this->optionEnabled($options, 'no-json')) {
            throw new InvalidUsageException('Options "--json" and "--no-json" cannot be used together.');
        }

        if ($this->optionEnabled($options, 'json')) {
            return OutputFormat::Json;
        }

        if ($this->optionEnabled($options, 'no-json')) {
            return OutputFormat::Terminal;
        }

        return null;
    }

    /**
     * @param array<string, ParsedOption> $options
     */
    private function sizeFromOptions(array $options): ?OutputSize
    {
        $compact = $this->optionEnabled($options, 'compact');
        $full = $this->optionEnabled($options, 'full');

        if ($compact && $full) {
            throw new InvalidUsageException('Options "--compact" and "--full" cannot be used together.');
        }

        if ($compact) {
            return OutputSize::Compact;
        }

        if ($full) {
            return OutputSize::Full;
        }

        return null;
    }

    /**
     * @param array<string, ParsedOption> $options
     */
    private function boolFromOptions(array $options, string $positive, string $negative, bool $current): bool
    {
        $enabled = $this->optionEnabled($options, $positive);
        $disabled = $this->optionEnabled($options, $negative);

        if ($enabled && $disabled) {
            throw new InvalidUsageException(sprintf('Options "--%s" and "--%s" cannot be used together.', $positive, $negative));
        }

        return match (true) {
            $enabled => true,
            $disabled => false,
            default => $current,
        };
    }

    /**
     * @param array<string, ParsedOption> $options
     */
    private function optionEnabled(array $options, string $name): bool
    {
        return ($options[$name] ?? false) === true;
    }
}
