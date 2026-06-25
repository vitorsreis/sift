<?php

declare(strict_types=1);

use Sift\Tools\Testing\CloverCoverageParser;
use Tests\Support\FixtureProject;

it('normalizes clover project coverage and files below minimum', function (): void {
    $project = FixtureProject::create();
    $goodFile = $project->write('src/Good.php', '<?php');
    $badFile = $project->write('src/Bad.php', '<?php');
    $report = $project->write('build/clover.xml', sprintf(
        <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="%s">
      <metrics statements="10" coveredstatements="8"/>
    </file>
    <file name="%s">
      <metrics statements="10" coveredstatements="6"/>
    </file>
    <metrics statements="20" coveredstatements="14"/>
  </project>
</coverage>
XML,
        htmlspecialchars($goodFile, ENT_QUOTES | ENT_XML1),
        htmlspecialchars($badFile, ENT_QUOTES | ENT_XML1),
    ));

    $parsed = (new CloverCoverageParser())->parse($report, $project->root(), [$report], minimum: 75.0);

    expect($parsed->summary())->toBe([
        'coverage_percent' => 70.0,
        'coverage_min' => 75.0,
        'coverage_files_below_min' => 1,
    ]);
    expect($parsed->thresholdFailed())->toBeTrue();
    expect($parsed->items())->toBe([
        [
            'type' => 'coverage',
            'file' => 'src/Bad.php',
            'percent' => 60.0,
        ],
    ]);
});

it('lists every covered file when minimum is absent', function (): void {
    $project = FixtureProject::create();
    $goodFile = $project->write('src/Good.php', '<?php');
    $badFile = $project->write('src/Bad.php', '<?php');
    $report = $project->write('build/clover.xml', sprintf(
        <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="%s">
      <metrics statements="10" coveredstatements="8"/>
    </file>
    <file name="%s">
      <metrics statements="10" coveredstatements="6"/>
    </file>
    <metrics elements="20" coveredelements="14"/>
  </project>
</coverage>
XML,
        htmlspecialchars($goodFile, ENT_QUOTES | ENT_XML1),
        htmlspecialchars($badFile, ENT_QUOTES | ENT_XML1),
    ));

    $parsed = (new CloverCoverageParser())->parse($report, $project->root(), [$report], minimum: null);

    expect($parsed->summary())->toBe(['coverage_percent' => 70.0]);
    expect($parsed->thresholdFailed())->toBeFalse();
    expect($parsed->items())->toBe([
        [
            'type' => 'coverage',
            'file' => 'src/Bad.php',
            'percent' => 60.0,
        ],
        [
            'type' => 'coverage',
            'file' => 'src/Good.php',
            'percent' => 80.0,
        ],
    ]);
});
