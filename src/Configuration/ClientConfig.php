<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Configuration;

use Four\MarketplaceHttp\Authentication\AuthProviderInterface;
use Four\MarketplaceHttp\Configuration\RetryConfig;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Configuration class for HTTP client creation
 *
 * This class holds all configuration options for creating marketplace HTTP clients,
 * including base URLs, authentication, middleware options, and performance settings.
 */
readonly class ClientConfig
{
    /**
     * @param string $baseUri Base URI for API requests
     * @param array<string, mixed> $defaultHeaders Default headers to include with requests
     * @param array<string> $middleware List of middleware to apply
     * @param AuthProviderInterface|null $authProvider Authentication provider
     * @param RateLimiterFactory|null $rateLimiterFactory Rate limiter factory
     * @param LoggerInterface|null $logger Logger instance
     * @param CacheItemPoolInterface|null $cache Cache instance
     * @param RetryConfig|null $retryConfig Retry configuration
     * @param float $timeout Request timeout in seconds
     * @param int $maxRedirects Maximum number of redirects to follow
     * @param array<string, mixed> $additionalOptions Additional client options
     */
    public function __construct(
        public string $baseUri,
        public array $defaultHeaders = [],
        public array $middleware = [],
        public ?AuthProviderInterface $authProvider = null,
        public ?RateLimiterFactory $rateLimiterFactory = null,
        public ?LoggerInterface $logger = null,
        public ?CacheItemPoolInterface $cache = null,
        public ?RetryConfig $retryConfig = null,
        public float $timeout = 30.0,
        public int $maxRedirects = 3,
        public array $additionalOptions = []
    ) {}

    /**
     * Create a new configuration builder
     */
    public static function create(string $baseUri): ClientConfigBuilder
    {
        return new ClientConfigBuilder($baseUri);
    }

    /**
     * Create configuration with modified properties
     */
    public function with(
        ?string $baseUri = null,
        ?array $defaultHeaders = null,
        ?array $middleware = null,
        ?AuthProviderInterface $authProvider = null,
        ?RateLimiterFactory $rateLimiterFactory = null,
        ?LoggerInterface $logger = null,
        ?CacheItemPoolInterface $cache = null,
        ?RetryConfig $retryConfig = null,
        ?float $timeout = null,
        ?int $maxRedirects = null,
        ?array $additionalOptions = null
    ): self {
        return new self(
            $baseUri ?? $this->baseUri,
            $defaultHeaders ?? $this->defaultHeaders,
            $middleware ?? $this->middleware,
            $authProvider ?? $this->authProvider,
            $rateLimiterFactory ?? $this->rateLimiterFactory,
            $logger ?? $this->logger,
            $cache ?? $this->cache,
            $retryConfig ?? $this->retryConfig,
            $timeout ?? $this->timeout,
            $maxRedirects ?? $this->maxRedirects,
            $additionalOptions ?? $this->additionalOptions
        );
    }

    /**
     * Check if specific middleware is enabled
     */
    public function hasMiddleware(string $middleware): bool
    {
        return in_array($middleware, $this->middleware, true);
    }

    /**
     * Get merged headers including authentication headers
     *
     * @return array<string, mixed>
     */
    public function getMergedHeaders(): array
    {
        $headers = $this->defaultHeaders;

        if ($this->authProvider !== null) {
            $authHeaders = $this->authProvider->getAuthHeaders();
            $headers = array_merge($headers, $authHeaders);
        }

        return $headers;
    }

    /**
     * Convert configuration to Symfony HttpClient options
     *
     * @return array<string, mixed>
     */
    public function toHttpClientOptions(): array
    {
        $options = [
            'base_uri' => $this->baseUri,
            'headers' => $this->getMergedHeaders(),
            'timeout' => $this->timeout,
            'max_redirects' => $this->maxRedirects,
        ];

        return array_merge($options, $this->additionalOptions);
    }
}