<?php

declare(strict_types=1);

namespace Sift\Tools\Mago;

use Sift\Core\ErrorCode;
use Sift\Core\ItemType;
use Sift\Exceptions\UserFacingException;
use Sift\Tools\JsonOutputParser;
use Sift\Tools\Testing\ReportPathNormalizer;
use UnexpectedValueException;

final readonly class MagoFormatParser
{
    public function __construct(
        private JsonOutputParser $jsonOutputParser = new JsonOutputParser(),
        private ReportPathNormalizer $pathNormalizer = new ReportPathNormalizer(),
    ) {}

    public function parse(string $stdout, string $stderr, string $cwd): MagoReport
    {
        $raw = trim($stdout) !== '' ? $stdout : $stderr;

        if (trim($raw) === '') {
            return $this->report([]);
        }

        try {
            $document = $this->jsonOutputParser->parse($stdout, $stderr)->object();

            return $this->parseJson($document, $cwd);
        } catch (UserFacingException|UnexpectedValueException) {
            return $this->parseText($raw, $cwd);
        }
    }

    /**
     * @param array<string, mixed> $document
     */
    private function parseJson(array $document, string $cwd): MagoReport
    {
        $changedFiles = $this->stringList($document['changed_files'] ?? $document['files'] ?? [], 'changed_files');
        $parseErrors = $this->list($document['parse_errors'] ?? [], 'parse_errors');

        return $this->report($changedFiles, count($parseErrors), $cwd);
    }

    private function parseText(string $raw, string $cwd): MagoReport
    {
        $files = [];

        if (preg_match_all('/^diff --git a\/(.+?) b\/(.+)$/m', $raw, $matches) === 1) {
            foreach ($matches[2] as $file) {
                $files[$file] = true;
            }
        }

        $count = count($files);

        if (preg_match('/Found\s+(\d+)\s+file\(s\)/', $raw, $matches) === 1) {
            $count = max($count, (int) $matches[1]);
        }

        return $this->report(array_keys($files), cwd: $cwd, changedFilesCount: $count);
    }

    /**
     * @param list<string> $files
     */
    private function report(array $files, int $parseErrors = 0, ?string $cwd = null, ?int $changedFilesCount = null): MagoReport
    {
        $items = [];

        foreach ($files as $file) {
            $items[] = [
                'type' => ItemType::ChangedFile->value,
                'file' => $cwd === null ? $file : $this->pathNormalizer->normalize($file, $cwd),
            ];
        }

        $changedFilesCount ??= count($files);

        return new MagoReport(
            summary: [
                'changed_files' => $changedFilesCount,
                'parse_errors' => $parseErrors,
            ],
            items: $items,
            findings: $changedFilesCount + $parseErrors,
        );
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->invalidShape(sprintf('Mago format "%s" must be a list.', $field));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, string $field): array
    {
        $values = $this->list($value, $field);
        $strings = [];

        foreach ($values as $item) {
            if (! is_string($item)) {
                throw $this->invalidShape(sprintf('Mago format "%s" must contain only strings.', $field));
            }

            $strings[] = $item;
        }

        return $strings;
    }

    private function invalidShape(string $message): UserFacingException
    {
        return UserFacingException::withContext(
            errorCode: ErrorCode::ParseFailure,
            message: 'Unable to parse Mago format output.',
            context: ['reason' => $message],
        );
    }
}
