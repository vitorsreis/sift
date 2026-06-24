<?php

declare(strict_types=1);

namespace Sift\Skills;

use Closure;
use InvalidArgumentException;
use JsonException;
use Sift\Console\InvalidUsageException;
use Sift\Core\ErrorCode;
use Sift\Exceptions\UserFacingException;

final readonly class SkillsShCatalogClient
{
    private const string DEFAULT_BASE_URL = 'https://skills.sh/api/search';

    /**
     * @var Closure(string, int, list<string>): array{status: int|null, body: string|null, error: string|null}
     */
    private Closure $fetcher;

    /**
     * @param null|Closure(string, int, list<string>): array{status: int|null, body: string|null, error: string|null} $fetcher
     */
    public function __construct(
        ?Closure $fetcher = null,
        private ?string $baseUrl = null,
        private int $timeoutSeconds = 10,
    ) {
        if ($timeoutSeconds < 1) {
            throw new InvalidArgumentException('Catalog timeout must be positive.');
        }

        $this->fetcher = $fetcher ?? $this->defaultFetcher();
    }

    /**
     * @return array<array-key, mixed>
     */
    public function search(string $query, int $limit = 10, ?string $owner = null): array
    {
        $query = trim($query);
        $owner = $this->owner($owner);

        if ($query === '') {
            throw new InvalidUsageException('skills find requires a query.');
        }

        if ($limit < 1) {
            throw new InvalidUsageException('skills find limit must be positive.');
        }

        $headers = [
            'Accept: application/json',
            'User-Agent: sift',
        ];
        $response = ($this->fetcher)($this->url($query, $limit, $owner), $this->timeoutSeconds, $headers);
        $status = $response['status'];
        $body = $response['body'];

        if ($status === null || $body === null) {
            $this->unavailable('The skill catalog could not be reached.', ['reason' => $response['error']]);
        }

        if ($status < 200 || $status >= 300) {
            $this->unavailable('The skill catalog returned an unsuccessful response.', ['status_code' => $status]);
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            $this->unavailable('The skill catalog returned invalid JSON.', ['reason' => $jsonException->getMessage()]);
        }

        if (! is_array($decoded)) {
            $this->unavailable('The skill catalog returned an unexpected response.');
        }

        return $decoded;
    }

    private function url(string $query, int $limit, ?string $owner = null): string
    {
        $baseUrl = $this->baseUrl();
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $parameters = [
            'q' => $query,
            'limit' => $limit,
        ];

        if ($owner !== null) {
            $parameters['owner'] = $owner;
        }

        return $baseUrl . $separator . http_build_query($parameters);
    }

    private function owner(?string $owner): ?string
    {
        if ($owner === null) {
            return null;
        }

        $owner = strtolower(trim($owner));

        if ($owner === '') {
            return null;
        }

        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38})$/', $owner) !== 1) {
            throw new InvalidUsageException('--owner must be a valid GitHub owner.');
        }

        return $owner;
    }

    private function baseUrl(): string
    {
        if (is_string($this->baseUrl) && trim($this->baseUrl) !== '') {
            return trim($this->baseUrl);
        }

        $environmentUrl = getenv('SKILLS_API_URL');

        if (is_string($environmentUrl) && trim($environmentUrl) !== '') {
            return trim($environmentUrl);
        }

        return self::DEFAULT_BASE_URL;
    }

    /**
     * @return Closure(string, int, list<string>): array{status: int|null, body: string|null, error: string|null}
     */
    private function defaultFetcher(): Closure
    {
        /**
         * @param list<string> $headers
         *
         * @return array{status: int|null, body: string|null, error: string|null}
         */
        $fetcher = static function (string $url, int $timeout, array $headers): array {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'header' => self::headerLines($headers),
                ],
            ]);
            $stream = @fopen($url, 'rb', false, $context);

            if (! is_resource($stream)) {
                return [
                    'status' => null,
                    'body' => null,
                    'error' => 'request_failed',
                ];
            }

            $body = stream_get_contents($stream);
            $meta = stream_get_meta_data($stream);
            fclose($stream);
            $responseHeaders = self::wrapperHeaders($meta['wrapper_data'] ?? null);

            return [
                'status' => self::statusCode($responseHeaders),
                'body' => is_string($body) ? $body : null,
                'error' => is_string($body) ? null : 'request_failed',
            ];
        };

        return $fetcher;
    }

    /**
     * @return list<string>
     */
    private static function wrapperHeaders(mixed $wrapperData): array
    {
        if (is_string($wrapperData)) {
            return [$wrapperData];
        }

        if (! is_array($wrapperData) || ! array_is_list($wrapperData)) {
            return [];
        }

        $headers = [];

        foreach ($wrapperData as $header) {
            if (is_string($header)) {
                $headers[] = $header;
            }
        }

        return $headers;
    }

    /**
     * @param array<array-key, mixed> $headers
     */
    private static function headerLines(array $headers): string
    {
        $lines = [];

        foreach ($headers as $header) {
            if (! is_string($header) || trim($header) === '') {
                throw new InvalidArgumentException('Catalog headers must be strings.');
            }

            $lines[] = $header;
        }

        return implode("\r\n", $lines);
    }

    /**
     * @param list<string> $headers
     */
    private static function statusCode(array $headers): ?int
    {
        $statusCode = null;

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(?P<status>\d{3})#', $header, $matches) === 1) {
                $statusCode = (int) $matches['status'];
            }
        }

        return $statusCode;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function unavailable(string $message, array $context = []): never
    {
        throw UserFacingException::withContext(
            errorCode: ErrorCode::SkillCatalogUnavailable,
            message: $message,
            context: $context,
        );
    }
}
