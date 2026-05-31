<?php

declare(strict_types=1);

use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;

it('parses clean json from stdout first', function (): void {
    $output = (new JsonOutputParser())->parse(
        stdout: '{"status":"ok"}',
        stderr: '{"status":"stderr"}',
    );

    expect($output->decoded())->toBe(['status' => 'ok']);
    expect($output->source())->toBe('stdout');
    expect($output->clean())->toBeTrue();
    expect($output->line())->toBeNull();
    expect($output->offset())->toBeNull();
});

it('falls back to clean json from stderr', function (): void {
    $output = (new JsonOutputParser())->parse(
        stdout: '',
        stderr: '{"status":"ok"}',
    );

    expect($output->decoded())->toBe(['status' => 'ok']);
    expect($output->source())->toBe('stderr');
    expect($output->clean())->toBeTrue();
});

it('finds noisy json by scanning candidate lines from the end', function (): void {
    $raw = implode("\n", [
        '[WARNING] memory limit is low',
        'progress 50%',
        '{"status":"old"}',
        'done',
        '  {"status":"new","items":[{"type":"issue"}]}',
    ]);

    $output = (new JsonOutputParser())->parse(stdout: $raw);

    expect($output->decoded())->toBe(['status' => 'new', 'items' => [['type' => 'issue']]]);
    expect($output->source())->toBe('stdout');
    expect($output->line())->toBe(5);
    expect($output->offset())->toBe(strrpos($raw, '  {') + 2);
    expect($output->clean())->toBeFalse();
});

it('parses noisy json arrays', function (): void {
    $output = (new JsonOutputParser())->parse(stdout: "header\n[{\"message\":\"issue\"}]");

    expect($output->decoded())->toBe([['message' => 'issue']]);
    expect($output->line())->toBe(2);
});

it('parses noisy json with trailing output', function (): void {
    $output = (new JsonOutputParser())->parse(stdout: "header\n{\"message\":\"brace } inside string\"}\ntrailing");

    expect($output->decoded())->toBe(['message' => 'brace } inside string']);
    expect($output->line())->toBe(2);
    expect($output->clean())->toBeFalse();
});

it('returns parse failure as a user-facing exception', function (): void {
    try {
        (new JsonOutputParser())->parse(stdout: '[WARNING] no json here', stderr: 'fatal');
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::ParseFailure);
        expect($userFacingException->getMessage())->toBe('Could not parse tool JSON output.');
        expect($userFacingException->context())->toMatchArray([
            'stdout' => '[WARNING] no json here',
            'stderr' => 'fatal',
        ]);

        return;
    }

    throw new RuntimeException('Expected parse failure.');
});
