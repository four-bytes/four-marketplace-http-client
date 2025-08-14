<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Transport;

/**
 * Transport response object
 *
 * Represents the response from an HTTP transport layer,
 * providing a consistent interface across different transport implementations.
 */
readonly class TransportResponse
{
    /**
     * @param int $statusCode HTTP status code
     * @param array<string, string|array<string>> $headers Response headers
     * @param string $body Response body
     * @param array<string, mixed> $info Additional response information
     */
    public function __construct(
        public int $statusCode,
        public array $headers,
        public string $body,
        public array $info = []
    ) {}

    /**
     * Get response status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get all response headers
     *
     * @return array<string, string|array<string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header value
     *
     * @return string|array<string>|null
     */
    public function getHeader(string $name): string|array|null
    {
        // Case-insensitive header lookup
        $lowercaseName = strtolower($name);
        
        foreach ($this->headers as $headerName => $headerValue) {
            if (strtolower($headerName) === $lowercaseName) {
                return $headerValue;
            }
        }
        
        return null;
    }

    /**
     * Get response body as string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Get response body as JSON array
     *
     * @return array<string, mixed>
     * @throws \JsonException If body is not valid JSON
     */
    public function getJsonBody(): array
    {
        $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        
        if (!is_array($decoded)) {
            throw new \JsonException('Response body is not a JSON object or array');
        }
        
        return $decoded;
    }

    /**
     * Get additional response information
     *
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * Get specific info value
     */
    public function getInfoValue(string $key): mixed
    {
        return $this->info[$key] ?? null;
    }

    /**
     * Check if response was successful (2xx status code)
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if response is a client error (4xx status code)
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if response is a server error (5xx status code)
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Check if response indicates rate limiting (429 status code)
     */
    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    /**
     * Get Content-Type header value
     */
    public function getContentType(): ?string
    {
        $contentType = $this->getHeader('Content-Type');
        
        if (is_array($contentType)) {
            return $contentType[0] ?? null;
        }
        
        return $contentType;
    }

    /**
     * Check if response is JSON
     */
    public function isJson(): bool
    {
        $contentType = $this->getContentType();
        
        if ($contentType === null) {
            return false;
        }
        
        return str_contains(strtolower($contentType), 'application/json');
    }

    /**
     * Get request duration from info if available
     */
    public function getRequestDuration(): ?float
    {
        return $this->getInfoValue('total_time');
    }

    /**
     * Get effective URL (final URL after redirects) if available
     */
    public function getEffectiveUrl(): ?string
    {
        return $this->getInfoValue('effective_url');
    }

    /**
     * Convert response to array for debugging
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status_code' => $this->statusCode,
            'headers' => $this->headers,
            'body_length' => strlen($this->body),
            'content_type' => $this->getContentType(),
            'is_successful' => $this->isSuccessful(),
            'is_json' => $this->isJson(),
            'info' => $this->info
        ];
    }
}