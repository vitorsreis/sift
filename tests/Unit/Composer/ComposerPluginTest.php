<?php

declare(strict_types=1);

use Composer\Command\BaseCommand;
use Composer\Console\Application as ComposerApplication;
use Composer\Plugin\Capability\CommandProvider;
use Sift\Composer\Command\ComposerSkillsCommand;
use Sift\Composer\Command\SiftCommand;
use Sift\Composer\SiftCommandProvider;
use Sift\Composer\SiftPlugin;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

it('exposes only the command provider capability', function (): void {
    expect((new SiftPlugin())->getCapabilities())->toBe([
        CommandProvider::class => SiftCommandProvider::class,
    ]);
});

it('provides sift and skills composer commands', function (): void {
    $commands = (new SiftCommandProvider())->getCommands();
    $names = array_map(
        static fn(Command $command): ?string => $command->getName(),
        $commands,
    );

    expect($names)->toBe(['sift', 'skills']);
});

it('bridges composer sift arguments into the application', function (): void {
    $captured = null;
    $command = new SiftCommand(
        static function (array $arguments, OutputInterface $output) use (&$captured): int {
            $captured = $arguments;
            $output->write('ok');

            return 7;
        },
    );

    $tester = composerCommandTester($command);
    $exitCode = $tester->execute([
        'arguments' => ['--compact', '--pretty', 'pest'],
    ]);

    expect($exitCode)->toBe(7);
    expect($captured)->toBe(['--compact', '--pretty', 'pest']);
    expect($tester->getDisplay())->toBe('ok');
});

it('keeps raw sift flags from argv input', function (): void {
    $captured = null;
    $command = new SiftCommand(
        static function (array $arguments, OutputInterface $output) use (&$captured): int {
            $captured = $arguments;
            $output->write('ok');

            return 0;
        },
    );

    $output = new BufferedOutput();
    $exitCode = attachComposerApplication($command)->run(new ArgvInput(['composer', 'sift', '--compact', '--pretty', 'pest']), $output);

    expect($exitCode)->toBe(0);
    expect($captured)->toBe(['--compact', '--pretty', 'pest']);
    expect($output->fetch())->toBe('ok');
});

it('bridges composer skills arguments into the skills command namespace', function (): void {
    $captured = null;
    $command = new ComposerSkillsCommand(
        static function (array $arguments, OutputInterface $output) use (&$captured): int {
            $captured = $arguments;
            $output->write('ok');

            return 0;
        },
    );

    $tester = composerCommandTester($command);
    $exitCode = $tester->execute([
        'arguments' => ['add', 'vitorsreis/sift', '--skill', 'sift'],
    ]);

    expect($exitCode)->toBe(0);
    expect($captured)->toBe(['skills', 'add', 'vitorsreis/sift', '--skill', 'sift']);
    expect($tester->getDisplay())->toBe('ok');
});

it('runs the real application through composer sift', function (): void {
    $tester = composerCommandTester(new SiftCommand());
    $exitCode = $tester->execute([
        'arguments' => ['--no-pretty', 'help'],
    ]);

    $payload = decodeComposerCommandPayload($tester->getDisplay());

    expect($exitCode)->toBe(0);
    expect($payload['tool'] ?? null)->toBe('sift');
    expect($payload['status'] ?? null)->toBe('passed');
    expect($payload['command'] ?? null)->toBe('help');
});

it('runs the real application through composer skills', function (): void {
    $tester = composerCommandTester(new ComposerSkillsCommand());
    $exitCode = $tester->execute([
        'arguments' => ['--no-pretty', 'list'],
    ]);

    $payload = decodeComposerCommandPayload($tester->getDisplay());

    expect($exitCode)->toBe(0);
    expect($payload['tool'] ?? null)->toBe('sift');
    expect($payload['status'] ?? null)->toBe('passed');
    expect($payload['total'] ?? null)->toBe(0);
});

/**
 * @return array<string, mixed>
 */
function decodeComposerCommandPayload(string $json): array
{
    $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($payload) || array_is_list($payload)) {
        throw new RuntimeException('Composer command payload must be an object.');
    }

    $normalized = [];

    foreach ($payload as $key => $value) {
        if (! is_string($key)) {
            throw new RuntimeException('Composer command payload must use string keys.');
        }

        $normalized[$key] = $value;
    }

    return $normalized;
}

function composerCommandTester(BaseCommand $command): CommandTester
{
    attachComposerApplication($command);

    return new CommandTester($command);
}

function attachComposerApplication(BaseCommand $command): BaseCommand
{
    $application = new ComposerApplication();
    $application->addCommand($command);

    return $command;
}
