<?php

declare(strict_types=1);

namespace Sift\Registry;

use InvalidArgumentException;
use Sift\Tools\Composer\ComposerToolAdapter;
use Sift\Tools\ComposerRequireChecker\ComposerRequireCheckerToolAdapter;
use Sift\Tools\ComposerUnused\ComposerUnusedToolAdapter;
use Sift\Tools\Deptrac\DeptracToolAdapter;
use Sift\Tools\Infection\InfectionToolAdapter;
use Sift\Tools\Mago\MagoToolAdapter;
use Sift\Tools\ParallelLint\ParallelLintToolAdapter;
use Sift\Tools\PhpCs\PhpcbfToolAdapter;
use Sift\Tools\PhpCs\PhpcsToolAdapter;
use Sift\Tools\PhpCsFixer\PhpCsFixerToolAdapter;
use Sift\Tools\PhpMd\PhpmdToolAdapter;
use Sift\Tools\PhpStan\PhpstanToolAdapter;
use Sift\Tools\Pint\PintToolAdapter;
use Sift\Tools\Psalm\PsalmToolAdapter;
use Sift\Tools\Rector\RectorToolAdapter;
use Sift\Tools\Testing\ParatestToolAdapter;
use Sift\Tools\Testing\PestToolAdapter;
use Sift\Tools\Testing\PhpunitToolAdapter;
use Sift\Tools\ToolAdapter;

final readonly class ToolRegistry implements ToolRegistryInterface
{
    /**
     * @var array<string, ToolAdapter>
     */
    private array $adaptersByName;

    /**
     * @var list<ToolAdapter>
     */
    private array $adapters;

    public function __construct(ToolAdapter ...$adapters)
    {
        $this->adapters = array_values($adapters);

        $adaptersByName = [];

        foreach ($adapters as $adapter) {
            $definition = $adapter->definition();

            foreach ([$definition->name(), ...$definition->aliases()] as $name) {
                $normalizedName = $this->normalizeName($name);

                if (isset($adaptersByName[$normalizedName])) {
                    throw new InvalidArgumentException(sprintf('Tool registry name "%s" is already registered.', $normalizedName));
                }

                $adaptersByName[$normalizedName] = $adapter;
            }
        }

        $this->adaptersByName = $adaptersByName;
    }

    public static function builtIns(): self
    {
        return new self(
            new PestToolAdapter(),
            new PhpunitToolAdapter(),
            new ParatestToolAdapter(),
            new PhpstanToolAdapter(),
            new PsalmToolAdapter(),
            new PhpcsToolAdapter(),
            new PhpcbfToolAdapter(),
            new RectorToolAdapter(),
            new PintToolAdapter(),
            new MagoToolAdapter(),
            new InfectionToolAdapter(),
            new DeptracToolAdapter(),
            new PhpCsFixerToolAdapter(),
            new PhpmdToolAdapter(),
            new ComposerUnusedToolAdapter(),
            new ComposerRequireCheckerToolAdapter(),
            new ParallelLintToolAdapter(),
            new ComposerToolAdapter(),
        );
    }

    public function find(string $name): ?ToolAdapter
    {
        $normalizedName = $this->normalizeName($name);

        return $this->adaptersByName[$normalizedName] ?? null;
    }

    /**
     * @return list<ToolAdapter>
     */
    public function all(): array
    {
        return $this->adapters;
    }

    private function normalizeName(string $name): string
    {
        $normalizedName = strtolower(trim($name));

        if ($normalizedName === '') {
            throw new InvalidArgumentException('Tool registry name cannot be empty.');
        }

        return $normalizedName;
    }
}
