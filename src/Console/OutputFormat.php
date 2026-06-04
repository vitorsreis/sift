<?php

declare(strict_types=1);

namespace Sift\Console;

enum OutputFormat: string
{
    case Terminal = 'terminal';
    case Json = 'json';
}
