<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Middleware;

use Four\MarketplaceHttp\Configuration\RetryConfig;
use Four\MarketplaceHttp\Exception\RetryableException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Middleware that adds retry functionality to HTTP requests
 *
 * Automatically retries failed requests based on configurable retry strategies,
 * with exponential backoff and configurable retry conditions.
 */
class RetryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RetryConfig $retryConfig,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly string $marketplace = 'general'
    ) {}

    public function wrap(HttpClientInterface $client): HttpClientInterface
    {
        return new RetryHttpClient(
            $client,
            $this->retryConfig,
            $this->logger,
            $this->marketplace
        );
    }

    public function getName(): string
    {
        return 'retry';
    }

    public function getPriority(): int
    {
        return 50; // Apply retries after rate limiting but before logging final result
    }
}

/**
 * HTTP Client decorator that adds retry functionality
 */
class RetryHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly RetryConfig $retryConfig,
        private readonly LoggerInterface $logger,
        private readonly string $marketplace
    ) {}

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $this->retryConfig->maxAttempts) {
            try {
                $response = $this->client->request($method, $url, $options);
                
                // Check if response status code indicates we should retry
                $statusCode = $response->getStatusCode();
                if ($attempt < $this->retryConfig->maxAttempts && $this->retryConfig->shouldRetryStatusCode($statusCode)) {
                    $this->logger->warning('HTTP request returned retryable status code', [
                        'marketplace' => $this->marketplace,
                        'method' => $method,
                        'url' => $this->sanitizeUrl($url),
                        'status_code' => $statusCode,
                        'attempt' => $attempt,
                        'max_attempts' => $this->retryConfig->maxAttempts
                    ]);
                    
                    $this->waitBeforeRetry($attempt);
                    $attempt++;
                    continue;
                }
                
                // Success or non-retryable error
                if ($attempt > 1) {
                    $this->logger->info('HTTP request succeeded after retries', [
                        'marketplace' => $this->marketplace,
                        'method' => $method,
                        'url' => $this->sanitizeUrl($url),
                        'attempts' => $attempt,
                        'status_code' => $statusCode
                    ]);
                }
                
                return $response;
                
            } catch (\Exception $exception) {
                $lastException = $exception;
                
                // Check if this exception is retryable
                if ($attempt < $this->retryConfig->maxAttempts && $this->shouldRetryException($exception)) {
                    $this->logger->warning('HTTP request failed with retryable exception', [
                        'marketplace' => $this->marketplace,
                        'method' => $method,
                        'url' => $this->sanitizeUrl($url),
                        'exception' => get_class($exception),
                        'message' => $exception->getMessage(),
                        'attempt' => $attempt,
                        'max_attempts' => $this->retryConfig->maxAttempts
                    ]);
                    
                    $this->waitBeforeRetry($attempt);
                    $attempt++;
                    continue;
                }
                
                // Not retryable or out of attempts
                break;
            }
        }

        // If we get here, we've exhausted all retry attempts
        if ($lastException !== null) {
            if ($attempt > 1) {
                $this->logger->error('HTTP request failed after all retry attempts', [
                    'marketplace' => $this->marketplace,
                    'method' => $method,
                    'url' => $this->sanitizeUrl($url),
                    'attempts' => $attempt - 1,
                    'final_exception' => get_class($lastException),
                    'final_message' => $lastException->getMessage()
                ]);

                // Wrap the final exception with retry context
                throw RetryableException::fromException(
                    $lastException,
                    $this->marketplace,
                    $this->extractOperationFromUrl($url),
                    $attempt - 1,
                    $this->retryConfig->maxAttempts,
                    0.0
                );
            }
            
            throw $lastException;
        }

        // This should not happen, but just in case
        throw new \RuntimeException('Unexpected end of retry loop');
    }

    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return new static(
            $this->client->withOptions($options),
            $this->retryConfig,
            $this->logger,
            $this->marketplace
        );
    }

    /**
     * Check if an exception should trigger a retry
     */
    private function shouldRetryException(\Exception $exception): bool
    {
        // Use configured retryable exceptions
        if ($this->retryConfig->shouldRetryException($exception)) {
            return true;
        }
        
        // Additional checks for HTTP client specific exceptions
        if ($exception instanceof TransportExceptionInterface) {
            return true; // Network issues are generally retryable
        }
        
        if ($exception instanceof ServerExceptionInterface) {
            return true; // Server errors (5xx) are retryable
        }
        
        if ($exception instanceof ClientExceptionInterface) {
            // Only retry specific client errors
            try {
                $statusCode = $exception->getResponse()->getStatusCode();
                return $this->retryConfig->shouldRetryStatusCode($statusCode);
            } catch (\Exception) {
                return false;
            }
        }
        
        return false;
    }

    /**
     * Wait before retry with exponential backoff
     */
    private function waitBeforeRetry(int $attempt): void
    {
        $delay = $this->retryConfig->calculateDelay($attempt);
        
        if ($delay > 0) {
            $this->logger->debug('Waiting before retry', [
                'marketplace' => $this->marketplace,
                'attempt' => $attempt,
                'delay_seconds' => $delay
            ]);
            
            usleep((int)($delay * 1000000)); // Convert seconds to microseconds
        }
    }

    /**
     * Extract operation name from URL for context
     */
    private function extractOperationFromUrl(string $url): string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';
        
        // Extract operation name from path
        $pathParts = explode('/', trim($path, '/'));
        
        if (count($pathParts) > 0) {
            return $pathParts[count($pathParts) - 1] ?: 'unknown';
        }
        
        return 'unknown';
    }

    /**
     * Remove sensitive information from URLs for logging
     */
    private function sanitizeUrl(string $url): string
    {
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
        
        return $sanitized;
    }
}