<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;

final class ResultMetaStamper
{
    public function stamp(NormalizedResult $result, ExecutionResult $executionResult): NormalizedResult
    {
        $meta = $result->meta;

        if (! array_key_exists('exit_code', $meta)) {
            $meta['exit_code'] = $executionResult->exitCode;
        }

        if (! array_key_exists('duration', $meta)) {
            $meta['duration'] = $executionResult->duration;
        }

        if (! is_string($meta['created_at'] ?? null) || $meta['created_at'] === '') {
            $meta['created_at'] = gmdate('c');
        }

        return $result->withMeta($meta);
    }
}
