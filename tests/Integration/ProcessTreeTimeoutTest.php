<?php

declare(strict_types=1);

use Sift\Core\PreparedCommand;
use Sift\Execution\ProcessSupervisor;
use Tests\Support\FixtureProject;

it('terminates child processes when a command times out', function (): void {
    $project = FixtureProject::create();
    $marker = $project->path('child-finished.txt');
    $child = 'usleep(800000); file_put_contents(' . var_export($marker, true) . ', "finished");';
    $parent = '$process = proc_open([' . var_export(PHP_BINARY, true) . ', "-r", ' . var_export($child, true) . '], [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes); usleep(1500000);';

    $result = (new ProcessSupervisor())->run(
        new PreparedCommand('parent', PHP_BINARY, ['-r', $parent], $project->root()),
        0.1,
    );

    usleep(1_000_000);

    expect($result->timedOut())->toBeTrue();
    expect($marker)->not->toBeFile();
});
