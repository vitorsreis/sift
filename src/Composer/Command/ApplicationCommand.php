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
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ApplicationCommand extends BaseCommand
{
    /**
     * @param null|Closure(list<string>, OutputInterface): int $runner
     */
    public function __construct(
        private readonly ?Closure $runner = null,
    ) {
        parent::__construct();
    }

    final protected function configureBridge(string $name, string $description): void
    {
        $this->setName($name)
            ->setDescription($description)
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
        if ($input instanceof ArgvInput) {
            return $input->getRawTokens(true);
        }

        $arguments = $input->getArgument('arguments');

        if (! is_array($arguments)) {
            return [];
        }

        $items = [];

        foreach ($arguments as $argument) {
            if (is_scalar($argument) || $argument instanceof Stringable) {
                $items[] = (string) $argument;
            }
        }

        return $items;
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
