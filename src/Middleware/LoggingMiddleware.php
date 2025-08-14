<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Middleware;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Middleware that adds comprehensive HTTP request and response logging
 *
 * This middleware logs all HTTP requests and responses with performance metrics,
 * error details, and marketplace-specific context for debugging and monitoring.
 */
class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $marketplace = 'general'
    ) {}

    public function wrap(HttpClientInterface $client): HttpClientInterface
    {
        return new LoggingHttpClient($client, $this->logger, $this->marketplace);
    }

    public function getName(): string
    {
        return 'logging';
    }

    public function getPriority(): int
    {
        return 100; // Apply logging early to capture all requests
    }
}

/**
 * HTTP Client decorator that adds comprehensive logging
 */
class LoggingHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $marketplace
    ) {}

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $startTime = microtime(true);
        $requestId = uniqid("{$this->marketplace}_", true);
        
        // Log request start
        $this->logRequest($requestId, $method, $url, $options);
        
        try {
            $response = $this->client->request($method, $url, $options);
            
            // Create a logged response wrapper
            return new LoggedResponse(
                $response,
                $this->logger,
                $this->marketplace,
                $requestId,
                $startTime
            );
            
        } catch (\Exception $exception) {
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->error("HTTP request failed", [
                'marketplace' => $this->marketplace,
                'request_id' => $requestId,
                'method' => $method,
                'url' => $this->sanitizeUrl($url),
                'duration_ms' => round($duration, 2),
                'error' => $exception->getMessage(),
                'error_class' => get_class($exception)
            ]);
            
            throw $exception;
        }
    }

    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return new static(
            $this->client->withOptions($options),
            $this->logger,
            $this->marketplace
        );
    }

    /**
     * Log request details
     */
    private function logRequest(string $requestId, string $method, string $url, array $options): void
    {
        $logData = [
            'marketplace' => $this->marketplace,
            'request_id' => $requestId,
            'method' => $method,
            'url' => $this->sanitizeUrl($url),
        ];

        // Add relevant options without sensitive data
        if (isset($options['query'])) {
            $logData['query'] = $options['query'];
        }
        
        if (isset($options['headers']['Content-Type'])) {
            $logData['content_type'] = $options['headers']['Content-Type'];
        }
        
        if (isset($options['body']) && is_string($options['body'])) {
            $logData['body_size'] = strlen($options['body']);
        }

        $this->logger->info("HTTP request started", $logData);
    }

    /**
     * Remove sensitive information from URLs
     */
    private function sanitizeUrl(string $url): string
    {
        // Remove query parameters that might contain sensitive data
        $parsed = parse_url($url);
        
        if ($parsed === false) {
            return $url;
        }
        
        $sanitized = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? 'unknown');
        
        if (isset($parsed['port'])) {
            $sanitized .= ':' . $parsed['port'];
        }
        
        if (isset($parsed['path'])) {
            $sanitized .= $parsed['path'];
        }
        
        // Only include safe query parameters
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            $safeParams = array_intersect_key($queryParams, [
                'limit' => true,
                'offset' => true,
                'page' => true,
                'sort' => true,
                'order' => true,
                'filter' => true,
                'marketplaceIds' => true,
                'version' => true
            ]);
            
            if (!empty($safeParams)) {
                $sanitized .= '?' . http_build_query($safeParams);
            }
        }
        
        return $sanitized;
    }
}

/**
 * Response wrapper that logs when response is accessed
 */
class LoggedResponse implements ResponseInterface
{
    private bool $logged = false;
    
    public function __construct(
        private readonly ResponseInterface $response,
        private readonly LoggerInterface $logger,
        private readonly string $marketplace,
        private readonly string $requestId,
        private readonly float $startTime
    ) {}

    public function getStatusCode(): int
    {
        $this->logResponseOnce();
        return $this->response->getStatusCode();
    }

    public function getHeaders(bool $throw = true): array
    {
        $this->logResponseOnce();
        return $this->response->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        $this->logResponseOnce();
        return $this->response->getContent($throw);
    }

    public function toArray(bool $throw = true): array
    {
        $this->logResponseOnce();
        return $this->response->toArray($throw);
    }

    public function cancel(): void
    {
        $this->response->cancel();
    }

    public function getInfo(string $type = null)
    {
        return $this->response->getInfo($type);
    }

    /**
     * Log response details (only once)
     */
    private function logResponseOnce(): void
    {
        if ($this->logged) {
            return;
        }
        
        $this->logged = true;
        $duration = (microtime(true) - $this->startTime) * 1000;
        
        try {
            $statusCode = $this->response->getStatusCode();
            $headers = $this->response->getHeaders(false);
            
            $logData = [
                'marketplace' => $this->marketplace,
                'request_id' => $this->requestId,
                'status_code' => $statusCode,
                'duration_ms' => round($duration, 2),
            ];

            // Add response size if available
            if (isset($headers['content-length'])) {
                $logData['response_size'] = (int)$headers['content-length'][0];
            }

            // Add rate limit info if available
            $rateLimitHeaders = [
                'x-amzn-ratelimit-limit',
                'x-amzn-ratelimit-remaining', 
                'x-ebay-api-analytics-daily-remaining',
                'x-discogs-ratelimit-remaining',
                'x-ratelimit-remaining',
                'x-ratelimit-limit',
                'retry-after'
            ];
            
            foreach ($rateLimitHeaders as $header) {
                $headerKey = strtolower($header);
                foreach ($headers as $name => $values) {
                    if (strtolower($name) === $headerKey) {
                        $logData['rate_limit'][$headerKey] = $values[0];
                        break;
                    }
                }
            }

            $logLevel = $statusCode >= 400 ? 'warning' : 'info';
            $this->logger->log($logLevel, "HTTP response received", $logData);
            
        } catch (\Exception $e) {
            $this->logger->error("Failed to log HTTP response", [
                'marketplace' => $this->marketplace,
                'request_id' => $this->requestId,
                'duration_ms' => round($duration, 2),
                'error' => $e->getMessage()
            ]);
        }
    }
}