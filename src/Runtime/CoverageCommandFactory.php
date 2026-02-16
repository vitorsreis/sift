<?php

declare(strict_types=1);

namespace Sift\Runtime;

use RuntimeException;

final readonly class CoverageCommandFactory
{
    /**
     * @param  list<string>  $loadedExtensions
     */
    public function __construct(
        private string $phpBinary,
        private array $loadedExtensions,
    ) {}

    /**
     * @return list<string>
     */
    public function build(string $projectRoot): array
    {
        $siftBinary = $projectRoot.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'sift';
        $cloverPath = $projectRoot.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'coverage'.DIRECTORY_SEPARATOR.'clover.xml';
        $command = ['pest', '--coverage', '--min=80'];

        if ($this->hasExtensionDriver()) {
            return [$this->phpBinary, $siftBinary, ...$command, '--coverage-clover', $cloverPath];
        }

        throw new RuntimeException(
            'No PHP coverage driver is available. Install Xdebug or PCOV to run `composer test:coverage`.',
        );
    }

    private function hasExtensionDriver(): bool
    {
        $extensions = array_map('strtolower', $this->loadedExtensions);

        return in_array('xdebug', $extensions, true) || in_array('pcov', $extensions, true);
    }
}
