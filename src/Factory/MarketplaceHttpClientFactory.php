<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Factory;

use Four\MarketplaceHttp\Configuration\ClientConfig;
use Four\MarketplaceHttp\Configuration\RetryConfig;
use Four\MarketplaceHttp\Middleware\LoggingMiddleware;
use Four\MarketplaceHttp\Middleware\MiddlewareInterface;
use Four\MarketplaceHttp\Middleware\RateLimitingMiddleware;
use Four\MarketplaceHttp\Middleware\RetryMiddleware;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Factory for creating marketplace-specific HTTP clients
 *
 * This factory creates HTTP clients optimized for marketplace API integrations,
 * with pre-configured middleware for rate limiting, logging, retries, and authentication.
 */
class MarketplaceHttpClientFactory implements HttpClientFactoryInterface
{
    /** @var array<string, MiddlewareInterface> */
    private array $availableMiddleware = [];

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly ?CacheItemPoolInterface $cache = null
    ) {
        $this->initializeMiddleware();
    }

    public function createClient(ClientConfig $config): HttpClientInterface
    {
        // Create base Symfony HTTP client
        $client = HttpClient::create($config->toHttpClientOptions());

        // Apply middleware in priority order (highest priority first)
        $middleware = $this->getMiddlewareForConfig($config);
        
        // Sort by priority (descending)
        uasort($middleware, fn(MiddlewareInterface $a, MiddlewareInterface $b) => $b->getPriority() <=> $a->getPriority());

        foreach ($middleware as $middlewareInstance) {
            $client = $middlewareInstance->wrap($client);
        }

        return $client;
    }

    public function createAmazonClient(ClientConfig $config): HttpClientInterface
    {
        // Apply Amazon-specific optimizations
        $amazonConfig = $config->with(
            defaultHeaders: array_merge([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'Four-MarketplaceHttp/1.0 (Amazon SP-API)'
            ], $config->defaultHeaders),
            retryConfig: $config->retryConfig ?? RetryConfig::forMarketplace('amazon')
        );

        // Ensure rate limiting is enabled for Amazon
        if (!$amazonConfig->hasMiddleware('rate_limiting') && $config->rateLimiterFactory !== null) {
            $amazonConfig = $amazonConfig->with(
                middleware: array_merge($amazonConfig->middleware, ['rate_limiting'])
            );
        }

        return $this->createClient($amazonConfig);
    }

    public function createEbayClient(ClientConfig $config): HttpClientInterface
    {
        // Apply eBay-specific optimizations
        $ebayConfig = $config->with(
            defaultHeaders: array_merge([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'Four-MarketplaceHttp/1.0 (eBay API)'
            ], $config->defaultHeaders),
            retryConfig: $config->retryConfig ?? RetryConfig::forMarketplace('ebay')
        );

        // Ensure rate limiting is enabled for eBay
        if (!$ebayConfig->hasMiddleware('rate_limiting') && $config->rateLimiterFactory !== null) {
            $ebayConfig = $ebayConfig->with(
                middleware: array_merge($ebayConfig->middleware, ['rate_limiting'])
            );
        }

        return $this->createClient($ebayConfig);
    }

    public function createDiscogsClient(ClientConfig $config): HttpClientInterface
    {
        // Apply Discogs-specific optimizations
        $discogsConfig = $config->with(
            defaultHeaders: array_merge([
                'Accept' => 'application/vnd.discogs.v2.discogs+json',
                'User-Agent' => 'Four-MarketplaceHttp/1.0 +https://4bytes.de'
            ], $config->defaultHeaders),
            retryConfig: $config->retryConfig ?? RetryConfig::forMarketplace('discogs')
        );

        // Discogs requires conservative rate limiting
        if (!$discogsConfig->hasMiddleware('rate_limiting') && $config->rateLimiterFactory !== null) {
            $discogsConfig = $discogsConfig->with(
                middleware: array_merge($discogsConfig->middleware, ['rate_limiting'])
            );
        }

        return $this->createClient($discogsConfig);
    }

    public function createBandcampClient(ClientConfig $config): HttpClientInterface
    {
        // Apply Bandcamp-specific optimizations
        $bandcampConfig = $config->with(
            defaultHeaders: array_merge([
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (compatible; Four-MarketplaceHttp/1.0)'
            ], $config->defaultHeaders),
            retryConfig: $config->retryConfig ?? RetryConfig::forMarketplace('bandcamp'),
            timeout: 15.0 // Shorter timeout for unofficial API
        );

        // Very conservative rate limiting for unofficial API
        if (!$bandcampConfig->hasMiddleware('rate_limiting') && $config->rateLimiterFactory !== null) {
            $bandcampConfig = $bandcampConfig->with(
                middleware: array_merge($bandcampConfig->middleware, ['rate_limiting'])
            );
        }

        return $this->createClient($bandcampConfig);
    }

    public function getAvailableMiddleware(): array
    {
        return array_keys($this->availableMiddleware);
    }

    /**
     * Create a rate limiter factory for a specific marketplace
     */
    public function createRateLimiterFactory(string $marketplace, array $config = []): RateLimiterFactory
    {
        $cache = $this->cache ?? new ArrayAdapter();
        $storage = new CacheStorage($cache);

        $defaultConfigs = [
            'amazon' => [
                'id' => 'amazon',
                'policy' => 'token_bucket',
                'limit' => 20,
                'rate' => ['interval' => '1 second', 'amount' => 20]
            ],
            'ebay' => [
                'id' => 'ebay',
                'policy' => 'fixed_window',
                'limit' => 5000,
                'rate' => ['interval' => '1 day']
            ],
            'discogs' => [
                'id' => 'discogs',
                'policy' => 'sliding_window',
                'limit' => 60,
                'rate' => ['interval' => '1 minute']
            ],
            'bandcamp' => [
                'id' => 'bandcamp',
                'policy' => 'token_bucket',
                'limit' => 2,
                'rate' => ['interval' => '1 second', 'amount' => 1]
            ]
        ];

        $marketplaceConfig = array_merge($defaultConfigs[$marketplace] ?? $defaultConfigs['amazon'], $config);

        return new RateLimiterFactory($marketplaceConfig, $storage);
    }

    /**
     * Initialize available middleware instances
     */
    private function initializeMiddleware(): void
    {
        $logger = $this->logger ?? new NullLogger();

        // Note: These are factory methods, actual instances created per request
        $this->availableMiddleware = [
            'logging' => 'logging',
            'rate_limiting' => 'rate_limiting', 
            'retry' => 'retry',
            'authentication' => 'authentication',
            'caching' => 'caching',
            'performance' => 'performance'
        ];
    }

    /**
     * Get middleware instances for a configuration
     *
     * @return array<string, MiddlewareInterface>
     */
    private function getMiddlewareForConfig(ClientConfig $config): array
    {
        $middleware = [];
        $logger = $config->logger ?? $this->logger ?? new NullLogger();

        foreach ($config->middleware as $middlewareName) {
            switch ($middlewareName) {
                case 'logging':
                    $marketplace = $this->extractMarketplaceFromConfig($config);
                    $middleware[$middlewareName] = new LoggingMiddleware($logger, $marketplace);
                    break;

                case 'rate_limiting':
                    if ($config->rateLimiterFactory !== null) {
                        $marketplace = $this->extractMarketplaceFromConfig($config);
                        $middleware[$middlewareName] = new RateLimitingMiddleware(
                            $config->rateLimiterFactory,
                            $logger,
                            $marketplace
                        );
                    }
                    break;

                case 'retry':
                    if ($config->retryConfig !== null) {
                        $marketplace = $this->extractMarketplaceFromConfig($config);
                        $middleware[$middlewareName] = new RetryMiddleware(
                            $config->retryConfig,
                            $logger,
                            $marketplace
                        );
                    }
                    break;

                // Additional middleware can be implemented here
                case 'authentication':
                case 'caching':
                case 'performance':
                    // Placeholder for future middleware implementations
                    break;
            }
        }

        return $middleware;
    }

    /**
     * Extract marketplace name from configuration
     */
    private function extractMarketplaceFromConfig(ClientConfig $config): string
    {
        $baseUri = $config->baseUri;
        
        if (str_contains($baseUri, 'amazon.com')) {
            return 'amazon';
        }
        
        if (str_contains($baseUri, 'ebay.com')) {
            return 'ebay';
        }
        
        if (str_contains($baseUri, 'discogs.com')) {
            return 'discogs';
        }
        
        if (str_contains($baseUri, 'bandcamp.com')) {
            return 'bandcamp';
        }
        
        return 'general';
    }
}