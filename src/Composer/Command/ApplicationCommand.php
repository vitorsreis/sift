<?php

declare(strict_types=1);

namespace Sift\Composer\Command;

use Closure;
use Composer\Command\BaseCommand;
use Sift\Console\Application;
use Stringable;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ApplicationCommand extends BaseCommand
{
    private readonly ?Closure $runner;

    /**
     * @param null|Closure(list<string>, OutputInterface): int|string $runner
     */
    public function __construct(
        Closure|string|null $runner = null,
    ) {
        $this->runner = $runner instanceof Closure ? $runner : null;

        parent::__construct(is_string($runner) ? $runner : null);
    }

    final protected function configureBridge(string $name, string $description): void
    {
        $this->setName($name)
            ->setDescription($description)
            ->addOption('compact', null, InputOption::VALUE_NONE, 'Keep Sift result output short.')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Show complete Sift result output.')
            ->addOption('pretty', 'p', InputOption::VALUE_NONE, 'Pretty-print Sift JSON output.')
            ->addOption('no-pretty', 'P', InputOption::VALUE_NONE, 'Disable Sift JSON pretty-printing.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Render Sift JSON output.')
            ->addOption('no-json', null, InputOption::VALUE_NONE, 'Force Sift terminal output.')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'Stream native tool output through Sift.')
            ->addOption('show-process', null, InputOption::VALUE_NONE, 'Show the prepared Sift process on STDERR.')
            ->addOption('no-show-process', null, InputOption::VALUE_NONE, 'Hide the prepared Sift process.')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Show Sift debug context on STDERR.')
            ->addOption('history', null, InputOption::VALUE_NONE, 'Force Sift history recording.')
            ->addOption('no-history', null, InputOption::VALUE_NONE, 'Skip Sift history recording.')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Use a specific Sift config file.')
            ->addArgument('arguments', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Sift command arguments');

        $this->ignoreValidationErrors();
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        unset($input, $output);
    }

    /**
     * @param list<string> $arguments
     */
    final protected function runSiftApplication(array $arguments, OutputInterface $output): int
    {
        if ($this->runner instanceof Closure) {
            return ($this->runner)($arguments, $output);
        }

        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $cwd = getcwd();

        return (new Application(
            stdoutWriter: static function (string $contents) use ($output): void {
                $output->write($contents);
            },
            stderrWriter: static function (string $contents) use ($errorOutput): void {
                $errorOutput->write($contents);
            },
            cwd: is_string($cwd) ? $cwd : '.',
        ))->run(['sift', ...$arguments]);
    }

    /**
     * @return list<string>
     */
    final protected function siftArguments(InputInterface $input): array
    {
        $originalArguments = $this->originalComposerArguments();

        if ($originalArguments !== []) {
            return $originalArguments;
        }

        if ($input instanceof ArgvInput) {
            $rawTokens = $this->rawTokens($input);

            if ($rawTokens !== []) {
                return $rawTokens;
            }
        }

        $arguments = $input->getArgument('arguments');

        if (! is_array($arguments)) {
            return [];
        }

        $items = $this->siftOptionTokens($input);

        foreach ($arguments as $argument) {
            if (is_scalar($argument) || $argument instanceof Stringable) {
                $items[] = (string) $argument;
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function rawTokens(InputInterface $input): array
    {
        if (! method_exists($input, 'getRawTokens')) {
            return [];
        }

        $tokens = $input->getRawTokens(true);

        return is_array($tokens) ? array_values(array_filter($tokens, is_string(...))) : [];
    }

    /**
     * @return list<string>
     */
    private function originalComposerArguments(): array
    {
        $argv = $_SERVER['argv'] ?? null;
        $command = $this->getName();

        if (! is_array($argv) || ! is_string($command) || $command === '') {
            return [];
        }

        $tokens = [];

        foreach ($argv as $token) {
            if (is_scalar($token) || $token instanceof Stringable) {
                $tokens[] = (string) $token;
            }
        }

        $index = array_search($command, $tokens, true);

        if (! is_int($index)) {
            return [];
        }

        if (($tokens[$index - 1] ?? null) === 'run-script') {
            return [];
        }

        return array_slice($tokens, $index + 1);
    }

    /**
     * @return list<string>
     */
    private function siftOptionTokens(InputInterface $input): array
    {
        $tokens = [];

        foreach ([
            'compact',
            'full',
            'pretty',
            'no-pretty',
            'json',
            'no-json',
            'raw',
            'show-process',
            'no-show-process',
            'debug',
            'history',
            'no-history',
        ] as $option) {
            if ($input->hasOption($option) && $input->getOption($option) === true) {
                $tokens[] = '--' . $option;
            }
        }

        if ($input->hasOption('config') && is_scalar($input->getOption('config'))) {
            $tokens[] = '--config';
            $tokens[] = (string) $input->getOption('config');
        }

        $phpIni = $input->hasOption('php-ini') ? $input->getOption('php-ini') : [];

        if (is_array($phpIni)) {
            foreach ($phpIni as $value) {
                if (is_scalar($value) || $value instanceof Stringable) {
                    $tokens[] = '-d';
                    $tokens[] = (string) $value;
                }
            }
        }

        return $tokens;
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    final protected function namespacedArguments(string $command, array $arguments): array
    {
        $leading = [];
        $remaining = [];
        $count = count($arguments);

        for ($index = 0; $index < $count; ++$index) {
            $argument = $arguments[$index];

            if ($argument === '--') {
                $remaining = array_slice($arguments, $index);

                break;
            }

            if (! $this->isSiftGlobalOption($argument)) {
                $remaining = array_slice($arguments, $index);

                break;
            }

            $leading[] = $argument;

            if ($this->siftGlobalOptionNeedsValue($argument) && array_key_exists($index + 1, $arguments)) {
                ++$index;
                $leading[] = $arguments[$index];
            }
        }

        if ($leading === [] && $remaining === []) {
            $remaining = $arguments;
        }

        return [...$leading, $command, ...$remaining];
    }

    private function isSiftGlobalOption(string $argument): bool
    {
        $option = explode('=', $argument, 2)[0];

        return in_array($option, [
            '--compact',
            '--full',
            '--pretty',
            '-p',
            '--no-pretty',
            '-P',
            '--json',
            '--no-json',
            '--raw',
            '--show-process',
            '--no-show-process',
            '--debug',
            '--history',
            '--no-history',
            '--config',
            '-c',
        ], true);
    }

    private function siftGlobalOptionNeedsValue(string $argument): bool
    {
        if (str_contains($argument, '=')) {
            return false;
        }

        return in_array($argument, ['--config', '-c'], true);
    }
}
