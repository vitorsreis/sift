<?php

declare(strict_types=1);

namespace Sift\Safety;

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\ToolContext;

final readonly class PolicyPipeline
{
    /**
     * @param list<Policy> $policies
     */
    public function __construct(
        private array $policies,
    ) {}

    public function assertAllowed(PreparedCommand $command, ToolContext $context, ToolConfig $config): void
    {
        $violations = [];

        foreach ($this->policies as $policy) {
            foreach ($policy->violations($command, $context, $config) as $violation) {
                $violations[] = $violation;
            }
        }

        if ($violations === []) {
            return;
        }

        if (count($violations) === 1) {
            $violation = $violations[0];

            throw UserFacingException::withContext(
                errorCode: $violation->code(),
                message: $violation->message(),
                context: [
                    'code' => $violation->code()->value,
                    'argument' => $violation->argument(),
                    'policy' => $violation->policy(),
                ],
            );
        }

        throw UserFacingException::withContext(
            errorCode: ErrorCode::PolicyBlocked,
            message: 'Execution blocked by multiple policies.',
            context: [
                'violations' => array_map(
                    static fn(PolicyViolation $violation): array => $violation->toPayload(),
                    $violations,
                ),
            ],
        );
    }
}
