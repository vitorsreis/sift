<?php

declare(strict_types=1);

use Sift\Config\ToolConfig;
use Sift\Core\ErrorCode;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Safety\Policy;
use Sift\Safety\PolicyPipeline;
use Sift\Safety\PolicyViolation;
use Sift\Tools\ToolContext;

it('allows execution when policies produce no violations', function (): void {
    $pipeline = new PolicyPipeline([
        new class implements Policy {
            public function violations(PreparedCommand $command, ToolContext $context, ToolConfig $config): array
            {
                return [];
            }
        },
    ]);

    $pipeline->assertAllowed(
        command: new PreparedCommand('pest', 'vendor/bin/pest'),
        context: new ToolContext('pest'),
        config: new ToolConfig('pest', true, null, [], 1800),
    );

    expect(true)->toBeTrue();
});

it('throws the specific error code for a single policy violation', function (): void {
    $pipeline = new PolicyPipeline([
        new class implements Policy {
            /**
             * @return PolicyViolation[]
             */
            public function violations(PreparedCommand $command, ToolContext $context, ToolConfig $config): array
            {
                return [new PolicyViolation(
                    code: ErrorCode::BlockedArgument,
                    message: 'Argument "--watch" is blocked.',
                    policy: 'fake_policy',
                    argument: '--watch',
                )];
            }
        },
    ]);

    try {
        $pipeline->assertAllowed(
            command: new PreparedCommand('pest', 'vendor/bin/pest', ['--watch']),
            context: new ToolContext('pest'),
            config: new ToolConfig('pest', true, null, [], 1800),
        );
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::BlockedArgument);
        expect($userFacingException->context())->toBe([
            'code' => 'blocked_argument',
            'argument' => '--watch',
            'policy' => 'fake_policy',
        ]);

        return;
    }

    throw new RuntimeException('Policy pipeline did not block execution.');
});

it('collects multiple violations in stable order', function (): void {
    $pipeline = new PolicyPipeline([
        new class implements Policy {
            /**
             * @return PolicyViolation[]
             */
            public function violations(PreparedCommand $command, ToolContext $context, ToolConfig $config): array
            {
                return [new PolicyViolation(ErrorCode::ToolDisabled, 'Tool disabled.', 'first')];
            }
        },
        new class implements Policy {
            /**
             * @return PolicyViolation[]
             */
            public function violations(PreparedCommand $command, ToolContext $context, ToolConfig $config): array
            {
                return [new PolicyViolation(ErrorCode::BlockedArgument, 'Argument blocked.', 'second', '--fix')];
            }
        },
    ]);

    try {
        $pipeline->assertAllowed(
            command: new PreparedCommand('pint', 'vendor/bin/pint', ['--fix']),
            context: new ToolContext('pint'),
            config: new ToolConfig('pint', false, null, ['--fix'], 1800),
        );
    } catch (UserFacingException $userFacingException) {
        expect($userFacingException->errorCode())->toBe(ErrorCode::PolicyBlocked);
        expect($userFacingException->context())->toBe([
            'violations' => [
                [
                    'code' => 'tool_disabled',
                    'message' => 'Tool disabled.',
                    'argument' => null,
                    'policy' => 'first',
                ],
                [
                    'code' => 'blocked_argument',
                    'message' => 'Argument blocked.',
                    'argument' => '--fix',
                    'policy' => 'second',
                ],
            ],
        ]);

        return;
    }

    throw new RuntimeException('Policy pipeline did not block execution.');
});
