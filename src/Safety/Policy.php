<?php

declare(strict_types=1);

namespace Sift\Safety;

use Sift\Config\ToolConfig;
use Sift\Core\PreparedCommand;
use Sift\Tools\ToolContext;

interface Policy
{
    /**
     * @return list<PolicyViolation>
     */
    public function violations(PreparedCommand $command, ToolContext $context, ToolConfig $config): array;
}
