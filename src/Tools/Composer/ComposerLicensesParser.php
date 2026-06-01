<?php

declare(strict_types=1);

namespace Sift\Tools\Composer;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use UnexpectedValueException;

final readonly class ComposerLicensesParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
    ) {}

    public function parse(string $stdout, string $stderr): ComposerReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $dependencies = $this->object($document['dependencies'] ?? [], 'dependencies');
        $items = [];
        $licenseCounts = [];

        foreach ($dependencies as $package => $dependency) {
            if ($package === '') {
                throw $this->invalidShape('Composer licenses "dependencies" must use package names as keys.');
            }

            $dependency = $this->object($dependency, 'dependencies.' . $package);
            $licenses = $this->licenses($dependency['license'] ?? []);

            foreach ($licenses as $license) {
                $licenseCounts[$license] = ($licenseCounts[$license] ?? 0) + 1;
            }

            $item = [
                'type' => ItemType::License->value,
                'package' => $package,
                'licenses' => $licenses,
            ];
            $version = $this->optionalString($dependency, 'version');

            if ($version !== null) {
                $item['version'] = $version;
            }

            $items[] = $item;
        }

        ksort($licenseCounts);

        return new ComposerReport(
            summary: [
                'dependencies' => count($dependencies),
                'licenses' => $licenseCounts,
            ],
            items: $items,
            extra: [
                'root_package' => [
                    'name' => $this->optionalString($document, 'name'),
                    'version' => $this->optionalString($document, 'version'),
                    'licenses' => $this->licenses($document['license'] ?? []),
                ],
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function licenses(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        if ($value === []) {
            return ['UNKNOWN'];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->invalidShape('Composer licenses "license" must be a list or string.');
        }

        $licenses = [];

        foreach ($value as $license) {
            if (! is_string($license) || $license === '') {
                throw $this->invalidShape('Composer licenses "license" must contain only non-empty strings.');
            }

            $licenses[] = $license;
        }

        return $licenses;
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if ($value === []) {
            return [];
        }

        if (! is_array($value) || array_is_list($value)) {
            throw $this->invalidShape(sprintf('Composer licenses "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('Composer licenses "%s" must use string keys.', $field));
            }

            $object[$key] = $item;
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function optionalString(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse Composer licenses JSON output.',
            hint: 'Use --debug to inspect limited raw output snippets.',
            context: ['reason' => $message],
        );
    }
}
