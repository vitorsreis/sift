<?php

declare(strict_types=1);

namespace Sift\Core;

enum RunStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Changed = 'changed';
    case Error = 'error';
}
