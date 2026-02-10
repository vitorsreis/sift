<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Exceptions\UserFacingException;
use Sift\Registry\ToolRegistry;

final class InitService
{
    public function __construct(
        private readonly ToolLocator $toolLocator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function initialize(string $cwd, bool $force, ToolRegistry $registry): array
    {
        $path = $cwd.DIRECTORY_SEPARATOR.'sift.json';
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

            $tools[$tool->name()] = $tool->initConfig();
        }

        ksort($tools);

        $document = [
            'history' => [
                'enabled' => true,
            ],
            'output' => [
                'format' => 'json',
                'size' => 'normal',
            ],
            'tools' => (object) $tools,
        ];

        file_put_contents(
            $path,
            json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );

        return [
            'status' => 'initialized',
            'path' => $path,
            'overwritten' => $existed && $force,
            'tools' => array_keys($tools),
            'detected' => $detected,
        ];
    }
}
