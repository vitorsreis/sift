<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Exceptions\UserFacingException;
use Sift\Registry\ToolRegistry;

final class InitService
{
    public function __construct(
        private readonly ToolLocator $toolLocator,
        private readonly ConfigDocumentManager $configDocumentManager,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function initialize(string $cwd, bool $force, ToolRegistry $registry, ?string $configPath = null): array
    {
        $path = $this->configDocumentManager->path($cwd, $configPath);
        $existed = is_file($path);

        if ($existed && ! $force) {
            throw UserFacingException::configAlreadyExists($path);
        }

        $detected = [];
        $tools = [];

        foreach ($registry->all() as $tool) {
            $resolved = $this->toolLocator->locate($cwd, $tool->discoveryCandidates());

            if ($resolved === null) {
                continue;
            }

            $detected[] = [
                'tool' => $tool->name(),
                'path' => $resolved['path'],
            ];

            $tools[$tool->name()] = [
                ...$tool->initConfig(),
                'toolBinary' => str_replace('\\', '/', $resolved['candidate']),
            ];
        }

        ksort($tools);

        $document = [
            'history' => [
                'enabled' => true,
                'max_files' => 50,
                'path' => '.sift/history',
            ],
            'output' => [
                'format' => 'json',
                'size' => 'normal',
                'show_process' => false,
            ],
            'tools' => (object) $tools,
        ];

        $this->configDocumentManager->write($cwd, $document, $configPath);

        return [
            'status' => 'initialized',
            'path' => $path,
            'overwritten' => $existed && $force,
            'tools' => array_keys($tools),
            'detected' => $detected,
        ];
    }
}
