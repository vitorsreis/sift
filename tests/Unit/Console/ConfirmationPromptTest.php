<?php

declare(strict_types=1);

use Sift\Console\ConfirmationPrompt;
use Sift\Console\InvalidUsageException;

it('accepts explicit interactive confirmation', function (): void {
    $output = '';
    $prompt = new ConfirmationPrompt(
        interactive: static fn(): bool => true,
        reader: static fn(): string => "yes\n",
        writer: static function (string $message) use (&$output): void {
            $output .= $message;
        },
    );

    $prompt->confirm('Install skill sift?', color: false);

    expect($output)->toBe('Install skill sift? [y/N] ');
});

it('rejects declined and non interactive confirmations', function (): void {
    $output = '';
    $declined = new ConfirmationPrompt(
        interactive: static fn(): bool => true,
        reader: static fn(): string => "no\n",
        writer: static function (string $message) use (&$output): void {
            $output .= $message;
        },
    );
    $nonInteractive = new ConfirmationPrompt(
        interactive: static fn(): bool => false,
        reader: static fn(): string => "yes\n",
        writer: static function (string $message) use (&$output): void {
            $output .= $message;
        },
    );

    expect(function () use ($declined): void {
        $declined->confirm('Remove skill?');
    })
        ->toThrow(InvalidUsageException::class, 'Skill command cancelled.');
    expect(function () use ($nonInteractive): void {
        $nonInteractive->confirm('Remove skill?');
    })
        ->toThrow(InvalidUsageException::class, 'Mutating skill commands require --yes or --all in non-interactive mode.');
});
