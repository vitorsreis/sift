<?php

declare(strict_types=1);

namespace Sift\Console;

enum CliOptionType
{
    case Boolean;
    case String;
    case Integer;
}
