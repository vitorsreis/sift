<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Safety\MagoSafeModePolicy;
use Sift\Safety\PolicyViolation;
use Sift\Tools\ToolContext;

/**
 * @return list<PolicyViolation>
 */
function magoSafeModeViolations(string ...$arguments): array
{
    return magoSafeModeViolationsFor('mago', ...$arguments);
}

/**
 * @return list<PolicyViolation>
 */
function magoSafeModeViolationsFor(string $tool, string ...$arguments): array
{
    $arguments = array_values($arguments);

    return (new MagoSafeModePolicy())->violations(
        command: new PreparedCommand($tool, 'vendor/bin/' . $tool, $arguments),
        context: new ToolContext($tool),
        config: new ToolConfig($tool, true, null, [], 1800),
    );
}

it('blocks mago baseline write modes', function (string $argument): void {
    $violations = magoSafeModeViolations('lint', $argument);

    expect($violations)->toHaveCount(1);
    expect($violations[0]->code())->toBe(ErrorCode::PolicyBlocked);
    expect($violations[0]->argument())->toBe($argument);
    expect($violations[0]->policy())->toBe('mago_safe_mode');
})->with(['--generate-baseline', '--remove-outdated-baseline-entries', '--backup-baseline']);

it('blocks mago analyze watch mode', function (string $subcommand): void {
    $violations = magoSafeModeViolations($subcommand, '--watch');

    expect($violations)->toHaveCount(1);
    expect($violations[0]->code())->toBe(ErrorCode::PolicyBlocked);
    expect($violations[0]->argument())->toBe('--watch');
    expect($violations[0]->policy())->toBe('mago_safe_mode');
})->with(['analyze', 'analyse']);

it('blocks mago fix without dry-run', function (string $argument, string ...$arguments): void {
    $violations = magoSafeModeViolations(...$arguments);

    expect($violations)->toHaveCount(1);
    expect($violations[0]->code())->toBe(ErrorCode::PolicyBlocked);
    expect($violations[0]->argument())->toBe($argument);
    expect($violations[0]->policy())->toBe('mago_safe_mode');
})->with([
    'lint fix' => ['--fix', 'lint', '--fix'],
    'analyze fix false dry-run' => ['--dry-run=false', 'analyze', '--fix', '--dry-run=false'],
    'guard fix zero dry-run' => ['--dry-run=0', 'guard', '--fix', '--dry-run=0'],
    'analyze fix separated false dry-run' => ['--dry-run', 'analyze', '--fix', '--dry-run', 'false'],
    'guard fix separated zero dry-run' => ['--dry-run', 'guard', '--fix', '--dry-run', '0'],
    'lint fix no dry-run' => ['--no-dry-run', 'lint', '--fix', '--no-dry-run'],
]);

it('blocks mago format write mode without a safe option', function (string ...$arguments): void {
    $violations = magoSafeModeViolations(...$arguments);

    expect($violations)->toHaveCount(1);
    expect($violations[0]->code())->toBe(ErrorCode::PolicyBlocked);
    expect($violations[0]->argument())->toBeNull();
    expect($violations[0]->policy())->toBe('mago_safe_mode');
})->with([
    'format' => ['format'],
    'format target' => ['format', 'src'],
    'format alias' => ['fmt', 'src'],
    'format after globals' => ['--colors=never', '--workspace', 'app', 'format', 'src'],
    'format false check' => ['format', 'src', '--check=false'],
    'format separated false check' => ['format', 'src', '--check', 'false'],
]);

it('allows mago safe modes', function (string ...$arguments): void {
    expect(magoSafeModeViolations(...$arguments))->toBe([]);
})->with([
    'default lint' => [],
    'lint' => ['lint'],
    'analyze dry-run fix' => ['analyze', '--fix', '--dry-run'],
    'analyze separated true dry-run fix' => ['analyze', '--fix', '--dry-run', 'true'],
    'guard short dry-run fix' => ['guard', '--fix', '-d'],
    'format check' => ['format', 'src', '--check'],
    'format separated true check' => ['format', 'src', '--check', 'true'],
    'format short check' => ['format', 'src', '-c'],
    'format dry-run' => ['format', 'src', '--dry-run'],
    'format stdin input' => ['format', '--stdin-input'],
    'format short stdin input' => ['format', '-i'],
    'global options before subcommand' => ['--colors=never', '--workspace=app', 'format', 'src', '--check'],
]);

it('ignores non-mago tools', function (): void {
    expect(magoSafeModeViolationsFor('pint', 'format'))->toBe([]);
});
