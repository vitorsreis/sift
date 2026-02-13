<?php

declare(strict_types=1);

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\BlockedArgumentsPolicy;
use Sift\Runtime\PolicyRunner;
use Sift\Runtime\ToolEnabledPolicy;
use Sift\Runtime\ToolInstalledPolicy;
use Sift\Runtime\ToolLocator;

it('applies enabled, blocked argument, and installed policies in sequence', function (): void {
    $cwd = makeTempDirectory();

    try {
        createProjectTool($cwd, 'demo.bat', "@echo off\r\n");

        $tool = new class implements ToolAdapterInterface
        {
            public function name(): string
            {
                return 'demo';
            }

            public function installHint(): string
            {
                return 'Install demo.';
            }

            public function discoveryCandidates(): array
            {
                return ['vendor/bin/demo.bat'];
            }

            public function initConfig(): array
            {
                return ['enabled' => true];
            }

            public function detectContext(array $arguments): array
            {
                return [];
            }

            public function prepare(string $cwd, array $arguments, array $context): PreparedCommand
            {
                return new PreparedCommand([], $cwd);
            }

            public function parse(ExecutionResult $executionResult, PreparedCommand $preparedCommand, array $context): NormalizedResult
            {
                return new NormalizedResult('demo', 'passed');
            }
        };

        $runner = new PolicyRunner([
            new ToolEnabledPolicy,
            new BlockedArgumentsPolicy,
            new ToolInstalledPolicy(new ToolLocator),
        ]);

        expect(fn () => $runner->enforce($cwd, $tool, ['run'], [
            'enabled' => true,
            'toolBinary' => 'vendor/bin/demo.bat',
            'defaultArgs' => [],
            'blockedArgs' => [],
        ]))->not->toThrow(UserFacingException::class);
    } finally {
        removeDirectory($cwd);
    }
});

it('fails fast when a policy rejects the tool execution', function (): void {
    $tool = new class implements ToolAdapterInterface
    {
        public function name(): string
        {
            return 'demo';
        }

        public function installHint(): string
        {
            return 'Install demo.';
        }

        public function discoveryCandidates(): array
        {
            return ['vendor/bin/demo.bat'];
        }

        public function initConfig(): array
        {
            return ['enabled' => true];
        }

        public function detectContext(array $arguments): array
        {
            return [];
        }

        public function prepare(string $cwd, array $arguments, array $context): PreparedCommand
        {
            return new PreparedCommand([], $cwd);
        }

        public function parse(ExecutionResult $executionResult, PreparedCommand $preparedCommand, array $context): NormalizedResult
        {
            return new NormalizedResult('demo', 'passed');
        }
    };

    $runner = new PolicyRunner([
        new ToolEnabledPolicy,
        new BlockedArgumentsPolicy,
    ]);

    try {
        $runner->enforce(sys_get_temp_dir(), $tool, ['--danger'], [
            'enabled' => false,
            'toolBinary' => null,
            'defaultArgs' => [],
            'blockedArgs' => ['--danger'],
        ]);
        $this->fail('Expected policy rejection.');
    } catch (UserFacingException $exception) {
        expect($exception->payload()['error']['code'])->toBe('tool_disabled');
    }
});
