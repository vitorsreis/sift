<?php

declare(strict_types=1);

use Sift\Console\CliParser;
use Sift\Console\CommandRouter;
use Sift\Console\InvalidUsageException;

/**
 * @return list<array{name: string, argv: list<string>, expected_handler: string, expected_arguments: list<string>, reason: string}>
 */
function preservedCommandFixtures(): array
{
    $fixtures = [];

    foreach (goldenFixtureList('preserved-commands.json') as $fixture) {
        $fixtures[] = [
            'name' => goldenString($fixture, 'name'),
            'argv' => goldenStringList($fixture, 'argv'),
            'expected_handler' => goldenString($fixture, 'expected_handler'),
            'expected_arguments' => goldenStringList($fixture, 'expected_arguments'),
            'reason' => goldenString($fixture, 'reason'),
        ];
    }

    return $fixtures;
}

/**
 * @return list<array{name: string, argv: list<string>, expected_error: string, reason: string}>
 */
function intentionalBreakFixtures(): array
{
    $fixtures = [];

    foreach (goldenFixtureList('intentional-breaks.json') as $fixture) {
        $fixtures[] = [
            'name' => goldenString($fixture, 'name'),
            'argv' => goldenStringList($fixture, 'argv'),
            'expected_error' => goldenString($fixture, 'expected_error'),
            'reason' => goldenString($fixture, 'reason'),
        ];
    }

    return $fixtures;
}

/**
 * @return list<array<string, mixed>>
 */
function goldenFixtureList(string $file): array
{
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'Golden' . DIRECTORY_SEPARATOR . $file;
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($decoded) || ! array_is_list($decoded)) {
        throw new RuntimeException(sprintf('Golden fixture "%s" must be a list.', $file));
    }

    $fixtures = [];

    foreach ($decoded as $item) {
        if (! is_array($item) || array_is_list($item)) {
            throw new RuntimeException(sprintf('Golden fixture "%s" must contain objects.', $file));
        }

        $fixture = [];

        foreach ($item as $key => $value) {
            if (! is_string($key)) {
                throw new RuntimeException(sprintf('Golden fixture "%s" must use string keys.', $file));
            }

            $fixture[$key] = $value;
        }

        $fixtures[] = $fixture;
    }

    return $fixtures;
}

/**
 * @param array<string, mixed> $fixture
 */
function goldenString(array $fixture, string $key): string
{
    $value = $fixture[$key] ?? null;

    if (! is_string($value)) {
        throw new RuntimeException(sprintf('Golden fixture field "%s" must be a string.', $key));
    }

    return $value;
}

/**
 * @param array<string, mixed> $fixture
 *
 * @return list<string>
 */
function goldenStringList(array $fixture, string $key): array
{
    $value = $fixture[$key] ?? null;

    if (! is_array($value) || ! array_is_list($value)) {
        throw new RuntimeException(sprintf('Golden fixture field "%s" must be a list.', $key));
    }

    $strings = [];

    foreach ($value as $item) {
        if (! is_string($item)) {
            throw new RuntimeException(sprintf('Golden fixture field "%s" must contain only strings.', $key));
        }

        $strings[] = $item;
    }

    return $strings;
}

it('preserves documented v2 command behavior from golden fixtures', function (): void {
    foreach (preservedCommandFixtures() as $fixture) {
        $route = CommandRouter::forSift()->route(CliParser::forSift()->parse($fixture['argv']));

        expect($route->handler())->toBe($fixture['expected_handler']);
        expect($route->arguments())->toBe($fixture['expected_arguments']);
    }
});

it('keeps intentional v2 breaks documented by golden fixtures', function (): void {
    foreach (intentionalBreakFixtures() as $fixture) {
        expect(fn(): mixed => CommandRouter::forSift()->route(CliParser::forSift()->parse($fixture['argv'])))
            ->toThrow(InvalidUsageException::class, $fixture['expected_error']);
    }
});
