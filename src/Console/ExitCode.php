<?php

declare(strict_types=1);

namespace Sift\Console;

enum ExitCode: int
{
    case Success = 0;
    case Findings = 1;
    case OperationalError = 2;
    case UserError = 3;
    case Interrupted = 130;
}
