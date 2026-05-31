<?php

declare(strict_types=1);

namespace Sift\Tools\ParallelLint;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use Sift\Tools\Testing\ReportPathNormalizer;
use UnexpectedValueException;

final readonly class ParallelLintParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): ParallelLintReport
    {
        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw $this->invalidShape($unexpectedValueException->getMessage());
        }

        $results = $this->object($document['results'] ?? [], 'results');
        $checkedFiles = $this->list($results['checkedFiles'] ?? [], 'results.checkedFiles');
        $filesWithSyntaxError = $this->list($results['filesWithSyntaxError'] ?? [], 'results.filesWithSyntaxError');
        $skippedFiles = $this->list($results['skippedFiles'] ?? [], 'results.skippedFiles');
        $errors = $this->list($results['errors'] ?? [], 'results.errors');
        $items = $this->items($errors, $cwd);

        return new ParallelLintReport(
            summary: [
                'checked_files' => count($checkedFiles),
                'files_with_syntax_error' => count($filesWithSyntaxError),
                'skipped_files' => count($skippedFiles),
                'errors' => count($items),
            ],
            items: $items,
            errors: count($items),
        );
    }

    /**
     * @param list<mixed> $errors
     *
     * @return list<array<string, mixed>>
     */
    private function items(array $errors, string $cwd): array
    {
        $items = [];

        foreach ($errors as $error) {
            $error = $this->object($error, 'results.errors[]');
            $kind = $this->stringValue($error, 'type');
            $item = [
                'type' => $kind === 'syntaxError' ? ItemType::SyntaxError->value : ItemType::Error->value,
                'file' => $this->pathNormalizer->normalize($this->stringValue($error, 'file'), $cwd),
            ];

            $line = $error['line'] ?? null;

            if (is_int($line)) {
                $item['line'] = $line;
            }

            $item['message'] = $this->stringValue($error, 'message');

            $normalizedMessage = $error['normalizeMessage'] ?? null;

            if (is_string($normalizedMessage) && $normalizedMessage !== '') {
                $item['normalized_message'] = $normalizedMessage;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw $this->invalidShape(sprintf('Parallel Lint "%s" must be an object.', $field));
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw $this->invalidShape(sprintf('Parallel Lint "%s" must use string keys.', $field));
            }

            $object[$key] = $item;
        }

        return $object;
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->invalidShape(sprintf('Parallel Lint "%s" must be a list.', $field));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw $this->invalidShape(sprintf('Parallel Lint "%s" must be a non-empty string.', $key));
        }

        return $value;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse Parallel Lint JSON output.',
            hint: 'Use --debug to inspect limited raw output snippets.',
            context: ['reason' => $message],
        );
    }
}
