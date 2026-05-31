<?php

declare(strict_types=1);

namespace Sift\Filesystem;

use JsonException;

/**
 * @phpstan-type JsonObject array<string, mixed>
 */
final readonly class JsonFile
{
    public function __construct(
        private AtomicWriter $writer = new AtomicWriter(),
    ) {}

    /**
     * @return JsonObject
     */
    public function readObject(string $path): array
    {
        $contents = file_get_contents($path);

        if (! is_string($contents) || trim($contents) === '') {
            throw FilesystemException::readFailed($path, 'The JSON file is empty.');
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw FilesystemException::readFailed($path, $jsonException->getMessage());
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw FilesystemException::readFailed($path, 'The JSON root must be an object.');
        }

        return $this->stringKeyedObject($decoded, $path);
    }

    /**
     * @param JsonObject $document
     */
    public function writeObject(string $path, array $document): void
    {
        $this->writer->write(
            $path,
            json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    /**
     * @param array<mixed> $object
     *
     * @return JsonObject
     */
    private function stringKeyedObject(array $object, string $path): array
    {
        $normalized = [];

        foreach ($object as $key => $value) {
            if (! is_string($key)) {
                throw FilesystemException::readFailed($path, 'The JSON object must use string keys.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
