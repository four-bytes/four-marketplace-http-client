<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Middleware;

use Four\MarketplaceHttp\Exception\RateLimitException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Middleware that applies rate limiting to HTTP requests
 *
 * Uses Symfony's RateLimiter component to control request frequency
 * and prevent API rate limit violations.
 */
class RateLimitingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiterFactory $rateLimiterFactory,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly string $marketplace = 'general'
    ) {}

    public function wrap(HttpClientInterface $client): HttpClientInterface
    {
        return new RateLimitingHttpClient(
            $client,
            $this->rateLimiterFactory,
            $this->logger,
            $this->marketplace
        );
    }

    public function getName(): string
    {
        return 'rate_limiting';
    }

    public function getPriority(): int
    {
        return 200; // Apply rate limiting early, before actual requests
    }
}

/**
 * HTTP Client decorator that applies rate limiting
 */
class RateLimitingHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly RateLimiterFactory $rateLimiterFactory,
        private readonly LoggerInterface $logger,
        private readonly string $marketplace
    ) {}

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        // Extract operation key from URL for specific rate limiting
        $operationKey = $this->extractOperationKey($url, $options);
        
        // Create rate limiter for this operation
        $rateLimiter = $this->rateLimiterFactory->create($operationKey);
        
        // Apply rate limiting
        $reservation = $rateLimiter->reserve(1);
        
        if (!$reservation->isAccepted()) {
            // Calculate wait time
            $waitDuration = $reservation->getWaitDuration();
            
            $this->logger->warning('Rate limit exceeded, waiting', [
                'marketplace' => $this->marketplace,
                'operation' => $operationKey,
                'wait_seconds' => $waitDuration,
                'url' => $this->sanitizeUrl($url)
            ]);
            
            if ($waitDuration > 0) {
                // Wait for the rate limit to reset
                usleep($waitDuration * 1000000); // Convert seconds to microseconds
                
                // Try again after waiting
                $reservation = $rateLimiter->reserve(1);
                if (!$reservation->isAccepted()) {
                    throw RateLimitException::forMarketplace(
                        $this->marketplace,
                        'Rate limit exceeded after waiting',
                        429,
                        null,
                        $operationKey
                    );
                }
            } else {
                throw RateLimitException::forMarketplace(
                    $this->marketplace,
                    'Rate limit exceeded',
                    429,
                    null,
                    $operationKey
                );
            }
        }
        
        // Make the actual request
        $response = $this->client->request($method, $url, $options);
        
        // Update rate limiter based on response headers if available
        $this->updateRateLimiterFromResponse($rateLimiter, $response, $operationKey);
        
        return $response;
    }

    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return new static(
            $this->client->withOptions($options),
            $this->rateLimiterFactory,
            $this->logger,
            $this->marketplace
        );
    }

    /**
     * Extract operation key from URL for specific rate limiting
     */
    private function extractOperationKey(string $url, array $options): string
    {
        // Parse URL to extract API endpoint
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        
        // Map URL patterns to operation keys based on marketplace
        $operationMappings = match ($this->marketplace) {
            'amazon' => [
                '/orders/v0/orders' => 'orders',
                '/listings/' => 'listings',
                '/feeds/' => 'feeds',
                '/reports/' => 'reports',
                '/catalog/v0/' => 'catalog',
                '/inventory/' => 'inventory',
                '/fba/inbound/' => 'fba_inbound',
                '/fba/outbound/' => 'fba_outbound',
                '/finances/v0/' => 'finances',
                '/tokens/2021-03-01/' => 'tokens',
                '/vendor/' => 'vendor'
            ],
            'ebay' => [
                '/sell/inventory/' => 'inventory',
                '/sell/fulfillment/' => 'orders',
                '/sell/account/' => 'account',
                '/sell/marketing/' => 'marketing',
                '/sell/analytics/' => 'analytics',
                '/commerce/translation/' => 'translation',
                '/buy/browse/' => 'browse',
                '/developer/analytics/' => 'analytics'
            ],
            'discogs' => [
                '/database/search' => 'search',
                '/artists/' => 'artists',
                '/releases/' => 'releases',
                '/masters/' => 'masters',
                '/users/' => 'users',
                '/marketplace/' => 'marketplace',
                '/oauth/' => 'oauth'
            ],
            default => [
                '/api/' => 'api',
                '/v1/' => 'v1',
                '/v2/' => 'v2'
            ]
        };
        
        // Find matching operation
        foreach ($operationMappings as $pattern => $operation) {
            if (str_contains($path, $pattern)) {
                return $operation;
            }
        }
        
        return 'general';
    }

    /**
     * Update rate limiter based on API response headers
     */
    private function updateRateLimiterFromResponse(
        $rateLimiter, 
        ResponseInterface $response, 
        string $operationKey
    ): void {
        try {
            $headers = $response->getHeaders(false);
            
            // Check for rate limit exceeded response
            $statusCode = $response->getStatusCode();
            if ($statusCode === 429) {
                $this->logger->warning('API rate limit exceeded', [
                    'marketplace' => $this->marketplace,
                    'operation' => $operationKey,
                    'status_code' => $statusCode
                ]);
                
                // Could implement dynamic rate limit adjustment here
                // based on the response headers
            }
            
            // Log rate limit information from headers
            $rateLimitInfo = [];
            
            // Amazon SP-API headers
            if (isset($headers['x-amzn-ratelimit-limit'])) {
                $rateLimitInfo['limit'] = (float)$headers['x-amzn-ratelimit-limit'][0];
            }
            if (isset($headers['x-amzn-ratelimit-remaining'])) {
                $rateLimitInfo['remaining'] = (float)$headers['x-amzn-ratelimit-remaining'][0];
            }
            
            // eBay API headers
            if (isset($headers['x-ebay-api-analytics-daily-remaining'])) {
                $rateLimitInfo['daily_remaining'] = (int)$headers['x-ebay-api-analytics-daily-remaining'][0];
            }
            
            // Discogs headers
            if (isset($headers['x-discogs-ratelimit-remaining'])) {
                $rateLimitInfo['remaining'] = (int)$headers['x-discogs-ratelimit-remaining'][0];
            }
            
            // Generic rate limit headers
            if (isset($headers['x-ratelimit-remaining'])) {
                $rateLimitInfo['remaining'] = (int)$headers['x-ratelimit-remaining'][0];
            }
            if (isset($headers['x-ratelimit-limit'])) {
                $rateLimitInfo['limit'] = (int)$headers['x-ratelimit-limit'][0];
            }
            
            if (!empty($rateLimitInfo)) {
                $this->logger->debug('Rate limit info from response', [
                    'marketplace' => $this->marketplace,
                    'operation' => $operationKey,
                    'rate_limit_info' => $rateLimitInfo
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->debug('Failed to parse rate limit headers', [
                'marketplace' => $this->marketplace,
                'operation' => $operationKey,
                'error' => $e->getMessage()
            ]);
        }
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