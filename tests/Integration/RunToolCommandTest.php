<?php

declare(strict_types=1);

use Sift\Config\ConfigDefaults;
use Tests\Support\CliRunner;
use Tests\Support\FakeBinary;
use Tests\Support\FixtureProject;

it('runs a direct tool command and records history by default', function (): void {
    $project = FixtureProject::create();
    $test = $project->write('tests/Feature/CheckoutTest.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'pest',
        writes: [
            '--log-junit' => runToolCommandJunitReport($test),
        ],
    );
    runToolCommandConfig($project, [
        'history' => ['enabled' => true],
        'output' => ['size' => 'full', 'pretty' => false, 'show_process' => false],
        'tools' => ['pest' => ['binary' => $fake->binary()]],
    ]);

    $result = CliRunner::run(['pest', '--filter', 'CheckoutTest'], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $history = runToolCommandHistoryDocuments($project);

    expect($result['exit_code'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($payload['tool'] ?? null)->toBe('pest');
    expect($payload['status'] ?? null)->toBe('passed');
    expect(runToolCommandObject($payload, 'summary'))->toMatchArray(['tests' => 1, 'passed' => 1]);
    expect(runToolCommandObject($payload, 'meta')['filter'] ?? null)->toBe('CheckoutTest');
    expect($fake->argv())->toContain('--filter', 'CheckoutTest', '--log-junit');
    expect($history)->toHaveCount(1);
    expect($history[0]['tool'] ?? null)->toBe('pest');
    expect($history[0]['status'] ?? null)->toBe('passed');
    expect(runToolCommandObject($history[0], 'payload')['tool'] ?? null)->toBe('pest');
});

it('streams raw tool output, preserves native exit code and skips history', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'composer',
        stdout: 'native stdout',
        stderr: 'native stderr',
        exitCode: 7,
    );
    runToolCommandConfig($project, [
        'history' => ['enabled' => true],
        'tools' => ['composer' => ['binary' => $fake->binary()]],
    ]);

    $result = CliRunner::run(['--raw', 'composer', 'audit'], $project->root());

    expect($result)->toBe([
        'exit_code' => 7,
        'stdout' => 'native stdout',
        'stderr' => 'native stderr',
    ]);
    expect(runToolCommandHistoryDocuments($project))->toBe([]);
});

it('writes the prepared process to stderr when enabled by config', function (): void {
    $project = FixtureProject::create();
    $test = $project->write('tests/Feature/CheckoutTest.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'pest',
        writes: ['--log-junit' => runToolCommandJunitReport($test)],
    );
    runToolCommandConfig($project, [
        'history' => ['enabled' => false],
        'output' => ['size' => 'full', 'pretty' => false, 'show_process' => true],
        'tools' => ['pest' => ['binary' => $fake->binary()]],
    ]);

    $result = CliRunner::run(['pest', '--filter', 'CheckoutTest'], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $process = CliRunner::decode($result['stderr']);
    $command = runToolCommandList($process, 'command');

    expect($result['exit_code'])->toBe(0);
    expect($payload['tool'] ?? null)->toBe('pest');
    expect($process)->toMatchArray([
        'tool' => 'pest',
        'type' => 'process',
        'cwd' => $project->root(),
    ]);
    expect($command)->toContain('--filter', 'CheckoutTest', '--log-junit');
});

it('writes the prepared process to stderr when enabled by option', function (): void {
    $project = FixtureProject::create();
    $test = $project->write('tests/Feature/CheckoutTest.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'pest',
        writes: ['--log-junit' => runToolCommandJunitReport($test)],
    );
    runToolCommandConfig($project, [
        'history' => ['enabled' => false],
        'output' => ['size' => 'full', 'pretty' => false, 'show_process' => false],
        'tools' => ['pest' => ['binary' => $fake->binary()]],
    ]);

    $result = CliRunner::run(['--show-process', 'pest'], $project->root());
    $payload = CliRunner::decode($result['stdout']);
    $process = CliRunner::decode($result['stderr']);

    expect($result['exit_code'])->toBe(0);
    expect($payload['tool'] ?? null)->toBe('pest');
    expect($process['tool'] ?? null)->toBe('pest');
    expect($process['type'] ?? null)->toBe('process');
});

it('applies history overrides with no-history taking precedence', function (): void {
    $recordingProject = FixtureProject::create();
    $recordingTest = $recordingProject->write('tests/Feature/CheckoutTest.php', '<?php');
    $recordingFake = FakeBinary::create(
        project: $recordingProject,
        name: 'pest',
        writes: ['--log-junit' => runToolCommandJunitReport($recordingTest)],
    );
    runToolCommandConfig($recordingProject, [
        'history' => ['enabled' => false],
        'tools' => ['pest' => ['binary' => $recordingFake->binary()]],
    ]);

    $recordingResult = CliRunner::run(['--history', 'pest'], $recordingProject->root());

    $skippingProject = FixtureProject::create();
    $skippingTest = $skippingProject->write('tests/Feature/CheckoutTest.php', '<?php');
    $skippingFake = FakeBinary::create(
        project: $skippingProject,
        name: 'pest',
        writes: ['--log-junit' => runToolCommandJunitReport($skippingTest)],
    );
    $skippingProject->write('blocked-history', 'not a directory');
    runToolCommandConfig($skippingProject, [
        'history' => [
            'enabled' => true,
            'path' => 'blocked-history',
        ],
        'tools' => ['pest' => ['binary' => $skippingFake->binary()]],
    ]);

    $skippingResult = CliRunner::run(['--history', '--no-history', 'pest'], $skippingProject->root());

    expect($recordingResult['exit_code'])->toBe(0);
    expect(runToolCommandHistoryDocuments($recordingProject))->toHaveCount(1);
    expect($skippingResult['exit_code'])->toBe(0);
    expect(runToolCommandHistoryDocuments($skippingProject))->toBe([]);
});

it('records tool errors and exposes the run id when history is enabled', function (): void {
    $project = FixtureProject::create();
    $fake = FakeBinary::create(
        project: $project,
        name: 'composer',
        stdout: 'not-json',
        stderr: 'native failure',
        exitCode: 1,
    );
    runToolCommandConfig($project, [
        'history' => ['enabled' => true],
        'output' => ['size' => 'full', 'pretty' => false, 'show_process' => false],
        'tools' => ['composer' => ['binary' => $fake->binary()]],
    ]);

    $result = CliRunner::run(['composer', 'audit'], $project->root());
    $payload = CliRunner::decode($result['stderr']);
    $error = runToolCommandObject($payload, 'error');
    $history = runToolCommandHistoryDocuments($project);
    $runId = runToolCommandString($error, 'run_id');

    expect($result['exit_code'])->toBe(2);
    expect($result['stdout'])->toBe('');
    expect($payload['status'] ?? null)->toBe('error');
    expect($error['code'] ?? null)->toBe('parse_failure');
    expect($runId)->toMatch('/^[0-9a-z]{14}$/');
    expect($history)->toHaveCount(1);
    expect($history[0]['run_id'] ?? null)->toBe($runId);
    expect($history[0]['tool'] ?? null)->toBe('composer');
    expect(runToolCommandHistoryFileNames($project))->toBe([sprintf('sift_%s_composer.json', $runId)]);
    expect(runToolCommandObject(runToolCommandObject($history[0], 'payload'), 'error')['code'] ?? null)->toBe('parse_failure');
});

it('returns a history write error when required history cannot be stored', function (): void {
    $project = FixtureProject::create();
    $test = $project->write('tests/Feature/CheckoutTest.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'pest',
        writes: ['--log-junit' => runToolCommandJunitReport($test)],
    );
    $project->write('blocked-history', 'not a directory');
    runToolCommandConfig($project, [
        'history' => [
            'enabled' => true,
            'path' => 'blocked-history',
        ],
        'tools' => ['pest' => ['binary' => $fake->binary()]],
    ]);

    $result = CliRunner::run(['pest'], $project->root());
    $payload = CliRunner::decode($result['stderr']);
    $error = runToolCommandObject($payload, 'error');

    expect($result['exit_code'])->toBe(2);
    expect($result['stdout'])->toBe('');
    expect($payload['status'] ?? null)->toBe('error');
    expect($error['code'] ?? null)->toBe('history_write_failed');
});

it('returns a history write error when history is forced and cannot be stored', function (): void {
    $project = FixtureProject::create();
    $test = $project->write('tests/Feature/CheckoutTest.php', '<?php');
    $fake = FakeBinary::create(
        project: $project,
        name: 'pest',
        writes: ['--log-junit' => runToolCommandJunitReport($test)],
    );
    $project->write('blocked-history', 'not a directory');
    runToolCommandConfig($project, [
        'history' => [
            'enabled' => false,
            'path' => 'blocked-history',
        ],
        'tools' => ['pest' => ['binary' => $fake->binary()]],
    ]);

    $result = CliRunner::run(['--history', 'pest'], $project->root());
    $payload = CliRunner::decode($result['stderr']);
    $error = runToolCommandObject($payload, 'error');

    expect($result['exit_code'])->toBe(2);
    expect($result['stdout'])->toBe('');
    expect($error['code'] ?? null)->toBe('history_write_failed');
});

/**
 * @param array<string, mixed> $overrides
 */
function runToolCommandConfig(FixtureProject $project, array $overrides): void
{
    $history = array_replace(ConfigDefaults::history(), runToolCommandObject($overrides, 'history'));
    $output = array_replace(ConfigDefaults::output(), runToolCommandObject($overrides, 'output'));
    $tools = runToolCommandObject($overrides, 'tools');

    $project->writeJson('sift.json', [
        '$schema' => ConfigDefaults::schemaUrl(),
        'output' => $output,
        'history' => $history,
        'tools' => $tools,
    ]);
}

/**
 * @param array<string, mixed> $payload
 *
 * @return array<string, mixed>
 */
function runToolCommandObject(array $payload, string $key): array
{
    $value = $payload[$key] ?? [];

    if (! is_array($value) || ($value !== [] && array_is_list($value))) {
        throw new RuntimeException(sprintf('Expected object field "%s".', $key));
    }

    $object = [];

    foreach ($value as $field => $fieldValue) {
        if (! is_string($field)) {
            throw new RuntimeException(sprintf('Expected string keys in "%s".', $key));
        }

        $object[$field] = $fieldValue;
    }

    return $object;
}

/**
 * @param array<string, mixed> $payload
 */
function runToolCommandString(array $payload, string $key): string
{
    $value = $payload[$key] ?? null;

    if (! is_string($value) || $value === '') {
        throw new RuntimeException(sprintf('Expected non-empty string field "%s".', $key));
    }

    return $value;
}

/**
 * @param array<string, mixed> $payload
 *
 * @return list<mixed>
 */
function runToolCommandList(array $payload, string $key): array
{
    $value = $payload[$key] ?? [];

    if (! is_array($value) || ! array_is_list($value)) {
        throw new RuntimeException(sprintf('Expected list field "%s".', $key));
    }

    return $value;
}

/**
 * @return list<array<string, mixed>>
 */
function runToolCommandHistoryDocuments(FixtureProject $project): array
{
    $files = glob($project->path('.sift/history/runs/*.json'));

    if ($files === false) {
        throw new RuntimeException('Could not scan history fixtures.');
    }

    sort($files);
    $documents = [];

    foreach ($files as $file) {
        $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('History fixture must be a JSON object.');
        }

        $documents[] = runToolCommandStringKeyedObject($decoded);
    }

    return $documents;
}

/**
 * @return list<string>
 */
function runToolCommandHistoryFileNames(FixtureProject $project): array
{
    $files = glob($project->path('.sift/history/runs/*.json'));

    if ($files === false) {
        throw new RuntimeException('Could not scan history fixtures.');
    }

    sort($files);

    return array_map(basename(...), $files);
}

/**
 * @param array<mixed, mixed> $payload
 *
 * @return array<string, mixed>
 */
function runToolCommandStringKeyedObject(array $payload): array
{
    $object = [];

    foreach ($payload as $key => $value) {
        if (! is_string($key)) {
            throw new RuntimeException('Expected string-keyed object.');
        }

        $object[$key] = $value;
    }

    return $object;
}

function runToolCommandJunitReport(string $testFile): string
{
    return sprintf(
        <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite tests="1" failures="0" errors="0">
    <testcase name="it checks out" file="%s"/>
  </testsuite>
</testsuites>
XML,
        htmlspecialchars($testFile, ENT_QUOTES | ENT_XML1),
    );
}
