<?php

declare(strict_types=1);

namespace Sift\Tools;

use Sift\Contracts\ToolAdapterInterface;
use Sift\Core\ExecutionResult;
use Sift\Core\NormalizedResult;
use Sift\Core\PreparedCommand;
use Sift\Exceptions\UserFacingException;
use Sift\Runtime\ToolLocator;
use Sift\Tools\Concerns\DecodesJsonOutput;
use Sift\Tools\Concerns\DetectsCliOptions;
use Sift\Tools\Concerns\ResolvesToolCandidates;

final readonly class ComposerAuditToolAdapter implements ToolAdapterInterface
{
    use DecodesJsonOutput;
    use DetectsCliOptions;
    use ResolvesToolCandidates;

    public function __construct(
        private ToolLocator $toolLocator,
    ) {}

    public function name(): string
    {
        return 'composer-audit';
    }

    public function installHint(): string
    {
        return 'Install Composer first and make sure the `composer` command is available.';
    }

    /**
     * @return list<string>
     */
    public function discoveryCandidates(): array
    {
        return ['composer'];
    }

    /**
     * @return array<string, mixed>
     */
    public function initConfig(): array
    {
        return [
            'enabled' => true,
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    public function detectContext(array $arguments): array
    {
        return [
            'arguments' => $arguments,
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, mixed>  $context
     */
    public function prepare(string $cwd, array $arguments, array $context): PreparedCommand
    {
        $resolved = $this->toolLocator->locate($cwd, $this->resolveCandidates($context, $this->discoveryCandidates()));

        if ($resolved === null) {
            throw UserFacingException::toolNotInstalled($this->name(), $this->installHint());
        }

        $command = [...$resolved['command_prefix'], 'audit'];

        if (! $this->hasOption($arguments, '--format')) {
            $command[] = '--format=json';
        }

        return new PreparedCommand(
            command: [...$command, ...$arguments],
            cwd: $cwd,
            metadata: $context,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function parse(
        ExecutionResult $executionResult,
        PreparedCommand $preparedCommand,
        array $context,
    ): NormalizedResult {
        $decoded = $this->decodeJsonOutput($executionResult);

        if (! is_array($decoded)) {
            throw UserFacingException::parseFailure($this->name(), 'Unable to parse Composer audit JSON output.');
        }

        $advisories = is_array($decoded['advisories'] ?? null) ? $decoded['advisories'] : [];
        $items = [];

        foreach ($advisories as $package => $packageAdvisories) {
            if (! is_array($packageAdvisories)) {
                continue;
            }

            foreach ($packageAdvisories as $advisory) {
                if (! is_array($advisory)) {
                    continue;
                }

                $items[] = [
                    'package' => (string) $package,
                    'severity' => (string) ($advisory['severity'] ?? 'unknown'),
                    'advisory_id' => (string) ($advisory['advisoryId'] ?? ''),
                    'title' => (string) ($advisory['title'] ?? ''),
                    'cve' => (string) ($advisory['cve'] ?? ''),
                    'link' => (string) ($advisory['link'] ?? ''),
                ];
            }
        }

        return new NormalizedResult(
            tool: $this->name(),
            status: $items === [] ? 'passed' : 'failed',
            summary: [
                'vulnerabilities' => count($items),
                'packages' => count($advisories),
            ],
            items: $items,
            meta: [
                'exit_code' => $executionResult->exitCode,
                'duration' => $executionResult->duration,
                'command' => $preparedCommand->command,
            ],
        );
    }
}
