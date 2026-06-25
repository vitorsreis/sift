<?php

declare(strict_types=1);

use Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit');

function stripSiftAnsi(string $output): string
{
    return preg_replace('/\033\[[0-9;?]*[A-Za-z]/', '', $output) ?? $output;
}
