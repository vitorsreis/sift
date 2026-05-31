<?php

declare(strict_types=1);

namespace Sift\Console;

enum OutputSize: string
{
    case Compact = 'compact';
    case Normal = 'normal';
    case Full = 'full';
}
