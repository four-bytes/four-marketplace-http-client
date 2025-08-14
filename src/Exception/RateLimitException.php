<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Exception;

/**
 * Exception thrown when rate limits are exceeded
 *
 * This exception is thrown when an HTTP request cannot be completed
 * due to rate limiting constraints, either from local rate limiting
 * or API rate limit responses.
 */
class RateLimitException extends HttpClientException
{
    public function __construct(
        string $message = 'Rate limit exceeded',
        int $code = 429,
        ?\Throwable $previous = null,
        ?string $marketplace = null,
        ?string $operation = null,
        private readonly ?int $retryAfter = null,
        private readonly ?int $remainingRequests = null,
        private readonly ?int $requestLimit = null
    ) {
        parent::__construct($message, $code, $previous, $marketplace, $operation);
    }

    /**
     * Get the number of seconds to wait before retrying
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Get the number of remaining requests allowed
     */
    public function getRemainingRequests(): ?int
    {
        return $this->remainingRequests;
    }

    /**
     * Get the total request limit
     */
    public function getRequestLimit(): ?int
    {
        return $this->requestLimit;
    }

    /**
     * Create exception from API response headers
     *
     * @param array<string, array<string>|string> $headers
     */
    public static function fromHeaders(
        array $headers,
        ?string $marketplace = null,
        ?string $operation = null
    ): self {
        // Normalize header names to lowercase
        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtolower($name)] = is_array($value) ? $value[0] : $value;
        }

        $retryAfter = null;
        $remaining = null;
        $limit = null;

        // Extract retry-after from various header formats
        if (isset($normalizedHeaders['retry-after'])) {
            $retryAfter = (int) $normalizedHeaders['retry-after'];
        } elseif (isset($normalizedHeaders['x-ratelimit-reset'])) {
            $resetTime = (int) $normalizedHeaders['x-ratelimit-reset'];
            $retryAfter = max(0, $resetTime - time());
        }

        // Extract remaining requests from various header formats
        if (isset($normalizedHeaders['x-ratelimit-remaining'])) {
            $remaining = (int) $normalizedHeaders['x-ratelimit-remaining'];
        } elseif (isset($normalizedHeaders['x-amzn-ratelimit-remaining'])) {
            $remaining = (int) $normalizedHeaders['x-amzn-ratelimit-remaining'];
        } elseif (isset($normalizedHeaders['x-discogs-ratelimit-remaining'])) {
            $remaining = (int) $normalizedHeaders['x-discogs-ratelimit-remaining'];
        }

        // Extract rate limit from various header formats
        if (isset($normalizedHeaders['x-ratelimit-limit'])) {
            $limit = (int) $normalizedHeaders['x-ratelimit-limit'];
        } elseif (isset($normalizedHeaders['x-amzn-ratelimit-limit'])) {
            $limit = (int) $normalizedHeaders['x-amzn-ratelimit-limit'];
        } elseif (isset($normalizedHeaders['x-discogs-ratelimit'])) {
            $limit = (int) $normalizedHeaders['x-discogs-ratelimit'];
        }

        $message = sprintf(
            'Rate limit exceeded%s%s%s',
            $remaining !== null ? " (remaining: {$remaining})" : '',
            $limit !== null ? " (limit: {$limit})" : '',
            $retryAfter !== null ? " (retry after: {$retryAfter}s)" : ''
        );

        return new self(
            $message,
            429,
            null,
            $marketplace,
            $operation,
            $retryAfter,
            $remaining,
            $limit
        );
    }
}