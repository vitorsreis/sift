<?php

declare(strict_types=1);

use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\Infection\InfectionParser;
use Tests\Support\FixtureProject;

it('normalizes infection summary metrics and mutation details', function (): void {
    $project = FixtureProject::create();
    $source = $project->write('src/Checkout.php', '<?php');
    $reportPath = $project->writeJson('build/infection-summary.json', infectionJsonReport($source));

    $report = (new InfectionParser())->parse($reportPath, $project->root(), [$reportPath]);

    expect($report->summary())->toBe([
        'total_mutants' => 9,
        'killed' => 3,
        'killed_by_tests' => 2,
        'killed_by_static_analysis' => 1,
        'escaped' => 2,
        'errored' => 1,
        'syntax_errors' => 1,
        'timed_out' => 1,
        'not_covered' => 1,
        'skipped' => 0,
        'ignored' => 0,
        'msi' => 55.56,
        'covered_msi' => 62.5,
        'mutation_code_coverage' => 88.89,
    ]);
    expect($report->items())->toBe([
        [
            'type' => ItemType::Mutation->value,
            'status' => 'escaped',
            'mutator' => 'PublicVisibility',
            'file' => 'src/Checkout.php',
            'line' => 17,
            'diff' => "- public function pay()\n+ private function pay()",
            'process_output' => 'Tests passed.',
        ],
        [
            'type' => ItemType::Mutation->value,
            'status' => 'timed_out',
            'mutator' => 'Plus',
            'file' => 'src/Checkout.php',
            'line' => 21,
            'diff' => "- return 1 + 1;\n+ return 1 - 1;",
            'process_output' => 'Timeout.',
        ],
    ]);
});

it('rejects infection reports outside generated artifacts', function (): void {
    $project = FixtureProject::create();
    $reportPath = $project->writeJson('build/infection-summary.json', infectionJsonReport(null));

    expect(fn(): mixed => (new InfectionParser())->parse($reportPath, $project->root(), []))
        ->toThrow(UserFacingException::class, 'Unable to parse Infection JSON output.');
});

/**
 * @return array<string, mixed>
 */
function infectionJsonReport(?string $source): array
{
    $mutation = [
        'mutator' => [
            'mutatorName' => 'PublicVisibility',
            'originalFilePath' => $source ?? 'src/Checkout.php',
            'originalStartLine' => 17,
        ],
        'diff' => "- public function pay()\n+ private function pay()",
        'processOutput' => 'Tests passed.',
    ];

    $timeout = [
        'mutator' => [
            'mutatorName' => 'Plus',
            'originalFilePath' => $source ?? 'src/Checkout.php',
            'originalStartLine' => 21,
        ],
        'diff' => "- return 1 + 1;\n+ return 1 - 1;",
        'processOutput' => 'Timeout.',
    ];

    return [
        'stats' => [
            'totalMutantsCount' => 9,
            'killedCount' => 2,
            'killedByStaticAnalysisCount' => 1,
            'notCoveredCount' => 1,
            'escapedCount' => 2,
            'errorCount' => 1,
            'syntaxErrorCount' => 1,
            'skippedCount' => 0,
            'ignoredCount' => 0,
            'timeOutCount' => 1,
            'msi' => 55.56,
            'mutationCodeCoverage' => 88.89,
            'coveredCodeMsi' => 62.5,
        ],
        'escaped' => [$mutation],
        'timeouted' => [$timeout],
        'killed' => [],
        'killedByStaticAnalysis' => [],
        'errored' => [],
        'syntaxErrors' => [],
        'uncovered' => [],
        'ignored' => [],
    ];
}
