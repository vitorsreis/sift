<?php

declare(strict_types=1);

namespace Sift\Runtime;

use Sift\Exceptions\UserFacingException;

final class ValidateService
{
    public function __construct(
        private readonly ConfigLoader $configLoader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function validate(string $cwd): array
    {
        $path = $this->configLoader->path($cwd);

        if (! is_file($path)) {
            throw UserFacingException::configNotFound($path);
        }

        $config = $this->configLoader->load($cwd);

        return [
            'status' => 'valid',
            'path' => $path,
            'tools' => array_keys($config['tools']),
            'output' => [
                'format' => $config['output']['format'],
                'size' => $config['output']['size'],
                'pretty' => $config['output']['pretty'],
            ],
            'history' => $config['history'],
        ];
    }
}
