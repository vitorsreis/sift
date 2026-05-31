<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Safety\MachineOutputPolicy;
use Sift\Safety\PolicyViolation;
use Sift\Tools\ToolContext;

/**
 * @return list<PolicyViolation>
 */
function machineOutputViolations(string $tool, bool $raw, string ...$arguments): array
{
    $arguments = array_values($arguments);

    return (new MachineOutputPolicy())->violations(
        command: new PreparedCommand($tool, 'vendor/bin/' . $tool, $arguments),
        context: new ToolContext($tool, raw: $raw),
        config: new ToolConfig($tool, true, null, [], 1800),
    );
}

it('blocks native non-json output formats outside raw mode', function (string $tool, string $argument, string ...$arguments): void {
    $violations = machineOutputViolations($tool, false, ...$arguments);

    expect($violations)->toHaveCount(1);
    expect($violations[0]->code())->toBe(ErrorCode::PolicyBlocked);
    expect($violations[0]->argument())->toBe($argument);
    expect($violations[0]->policy())->toBe('machine_output');
})->with([
    'phpstan error format' => ['phpstan', '--error-format=table', 'analyse', '--error-format=table'],
    'phpstan separated error format' => ['phpstan', '--error-format=table', 'analyse', '--error-format', 'table'],
    'phpcs report' => ['phpcs', '--report=full', '--report=full'],
    'psalm output format' => ['psalm', '--output-format=text', '--output-format=text'],
    'mago reporting format' => ['mago', '--reporting-format=plain', 'lint', '--reporting-format=plain'],
    'pint format' => ['pint', '--format=txt', '--format=txt'],
    'deptrac formatter' => ['deptrac', '--formatter=table', '--formatter=table'],
]);

it('allows json machine output formats', function (string $tool, string ...$arguments): void {
    expect(machineOutputViolations($tool, false, ...$arguments))->toBe([]);
})->with([
    'phpstan' => ['phpstan', 'analyse', '--error-format=json'],
    'phpcs' => ['phpcs', '--report=json'],
    'psalm' => ['psalm', '--output-format=json'],
    'mago' => ['mago', 'lint', '--reporting-format=json'],
    'pint' => ['pint', '--format=json'],
    'deptrac' => ['deptrac', '--formatter=json'],
    'composer' => ['composer', 'audit', '--format=json'],
    'no output option' => ['pest', '--filter', 'CheckoutTest'],
]);

it('allows native non-json formats in raw mode', function (): void {
    expect(machineOutputViolations('phpstan', true, 'analyse', '--error-format=table'))->toBe([]);
});
