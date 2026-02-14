<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Exceptions\UserFacingException;
use Sift\Registry\ToolRegistry;

final readonly class AddService
{
    public function __construct(
        private ToolLocator $toolLocator,
        private ConfigDocumentManager $configDocumentManager,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function add(string $cwd, string $toolName, ToolRegistry $registry, ?string $configPath = null): array
    {
        $tool = $registry->find($toolName);

        if ($tool === null) {
            throw UserFacingException::unsupportedTool($toolName);
        }

        $resolved = $this->toolLocator->locate($cwd, $tool->discoveryCandidates());

        if ($resolved === null) {
            throw UserFacingException::toolNotInstalled($tool->name(), $tool->installHint());
        }

        $path = $this->configDocumentManager->path($cwd, $configPath);
        $configCreated = ! is_file($path);
        $document = $this->configDocumentManager->readOrDefault($cwd, $configPath);
        $tools = is_array($document['tools'] ?? null) ? $document['tools'] : [];
        $existing = is_array($tools[$tool->name()] ?? null) ? $tools[$tool->name()] : [];

        $tools[$tool->name()] = array_replace(
            $tool->initConfig(),
            ['toolBinary' => $this->portableBinary($resolved['candidate'])],
            $existing,
            ['enabled' => true],
        );
        $document['tools'] = $tools;

        $this->configDocumentManager->write($cwd, $document, $configPath);

        return [
            'status' => 'added',
            'tool' => $tool->name(),
            'path' => $path,
            'config_created' => $configCreated,
            'detected' => [
                'path' => $resolved['path'],
            ],
        ];
    }

    private function portableBinary(string $candidate): string
    {
        return str_replace('\\', '/', $candidate);
    }
}
