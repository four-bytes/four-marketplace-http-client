<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Configuration;

use Four\MarketplaceHttp\Authentication\AuthProviderInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Builder class for ClientConfig
 *
 * Provides a fluent interface for constructing ClientConfig instances
 * with validation and sensible defaults.
 */
class ClientConfigBuilder
{
    private string $baseUri;
    
    /** @var array<string, mixed> */
    private array $defaultHeaders = [];
    
    /** @var array<string> */
    private array $middleware = [];
    
    private ?AuthProviderInterface $authProvider = null;
    private ?RateLimiterFactory $rateLimiterFactory = null;
    private ?LoggerInterface $logger = null;
    private ?CacheItemPoolInterface $cache = null;
    private ?RetryConfig $retryConfig = null;
    private float $timeout = 30.0;
    private int $maxRedirects = 3;
    
    /** @var array<string, mixed> */
    private array $additionalOptions = [];

    public function __construct(string $baseUri)
    {
        $this->baseUri = $baseUri;
    }

    /**
     * Set default headers for all requests
     *
     * @param array<string, mixed> $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    /**
     * Add a single header
     */
    public function withHeader(string $name, string $value): self
    {
        $this->defaultHeaders[$name] = $value;
        return $this;
    }

    /**
     * Set User-Agent header
     */
    public function withUserAgent(string $userAgent): self
    {
        return $this->withHeader('User-Agent', $userAgent);
    }

    /**
     * Set Accept header
     */
    public function withAccept(string $accept): self
    {
        return $this->withHeader('Accept', $accept);
    }

    /**
     * Set Content-Type header
     */
    public function withContentType(string $contentType): self
    {
        return $this->withHeader('Content-Type', $contentType);
    }

    /**
     * Enable specific middleware
     *
     * @param string|array<string> $middleware
     */
    public function withMiddleware(string|array $middleware): self
    {
        if (is_string($middleware)) {
            $middleware = [$middleware];
        }
        
        foreach ($middleware as $name) {
            if (!in_array($name, $this->middleware, true)) {
                $this->middleware[] = $name;
            }
        }
        
        return $this;
    }

    /**
     * Enable logging middleware
     */
    public function withLogging(?LoggerInterface $logger = null): self
    {
        $this->logger = $logger;
        return $this->withMiddleware('logging');
    }

    /**
     * Enable rate limiting middleware
     */
    public function withRateLimit(?RateLimiterFactory $rateLimiterFactory = null): self
    {
        $this->rateLimiterFactory = $rateLimiterFactory;
        return $this->withMiddleware('rate_limiting');
    }

    /**
     * Enable retry middleware
     */
    public function withRetries(?RetryConfig $retryConfig = null): self
    {
        $this->retryConfig = $retryConfig ?? RetryConfig::default();
        return $this->withMiddleware('retry');
    }

    /**
     * Enable authentication middleware
     */
    public function withAuthentication(AuthProviderInterface $authProvider): self
    {
        $this->authProvider = $authProvider;
        return $this->withMiddleware('authentication');
    }

    /**
     * Enable caching middleware
     */
    public function withCaching(?CacheItemPoolInterface $cache = null): self
    {
        $this->cache = $cache;
        return $this->withMiddleware('caching');
    }

    /**
     * Enable performance monitoring middleware
     */
    public function withPerformanceMonitoring(): self
    {
        return $this->withMiddleware('performance');
    }

    /**
     * Set request timeout
     */
    public function withTimeout(float $timeout): self
    {
        if ($timeout <= 0) {
            throw new \InvalidArgumentException('Timeout must be greater than 0');
        }
        
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Set maximum redirects
     */
    public function withMaxRedirects(int $maxRedirects): self
    {
        if ($maxRedirects < 0) {
            throw new \InvalidArgumentException('Max redirects must be non-negative');
        }
        
        $this->maxRedirects = $maxRedirects;
        return $this;
    }

    /**
     * Add additional options for the HTTP client
     *
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): self
    {
        $this->additionalOptions = array_merge($this->additionalOptions, $options);
        return $this;
    }

    /**
     * Configure authentication using simple parameters
     */
    public function withAuth(string $type, string $credentials, array $options = []): self
    {
        match ($type) {
            'bearer' => $this->withHeader('Authorization', "Bearer {$credentials}"),
            'basic' => $this->withHeader('Authorization', 'Basic ' . base64_encode($credentials)),
            'api_key' => $this->withHeader('Authorization', $credentials),
            'token' => $this->withHeader('Authorization', "Token {$credentials}"),
            default => throw new \InvalidArgumentException("Unsupported auth type: {$type}")
        };
        
        return $this;
    }

    /**
     * Configure rate limiting with simple parameters
     * 
     * @param string $policy Policy type: 'token_bucket', 'fixed_window', 'sliding_window'
     * @param array<string, mixed> $config Rate limit configuration
     */
    public function withRateLimit(string $policy, array $config = []): self
    {
        // This would require the factory to create a rate limiter
        // For now, just store the configuration and let the factory handle it
        $this->additionalOptions['rate_limit_policy'] = $policy;
        $this->additionalOptions['rate_limit_config'] = $config;
        
        return $this->withMiddleware('rate_limiting');
    }

    /**
     * Configure retry logic with simple parameters
     */
    public function withRetries(int $maxAttempts, array $retryableStatusCodes = [429, 500, 502, 503, 504]): self
    {
        $retryConfig = RetryConfig::create()
            ->withMaxAttempts($maxAttempts)
            ->withRetryableStatusCodes($retryableStatusCodes)
            ->withBackoffStrategy('exponential');
            
        $this->retryConfig = $retryConfig;
        return $this->withMiddleware('retry');
    }

    /**
     * Configure for Amazon SP-API
     */
    public function forAmazon(): self
    {
        return $this
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/json')
            ->withUserAgent('Four-MarketplaceHttp/1.0 (Amazon SP-API)')
            ->withTimeout(30.0)
            ->withMiddleware(['logging', 'rate_limiting', 'retry']);
    }

    /**
     * Configure for eBay API
     */
    public function forEbay(): self
    {
        return $this
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/json')
            ->withUserAgent('Four-MarketplaceHttp/1.0 (eBay API)')
            ->withTimeout(25.0)
            ->withMiddleware(['logging', 'rate_limiting', 'retry']);
    }

    /**
     * Configure for Discogs API
     */
    public function forDiscogs(): self
    {
        return $this
            ->withHeader('Accept', 'application/vnd.discogs.v2.discogs+json')
            ->withUserAgent('Four-MarketplaceHttp/1.0 +https://4bytes.de')
            ->withTimeout(15.0)
            ->withMiddleware(['logging', 'rate_limiting', 'oauth_1a']);
    }

    /**
     * Configure for Bandcamp API
     */
    public function forBandcamp(): self
    {
        return $this
            ->withHeader('Accept', 'application/json')
            ->withUserAgent('Mozilla/5.0 (compatible; Four-MarketplaceHttp/1.0)')
            ->withTimeout(15.0)
            ->withMiddleware(['logging', 'rate_limiting']);
    }

    /**
     * Configure for development/testing with lenient settings
     */
    public function forDevelopment(): self
    {
        return $this
            ->withTimeout(60.0)
            ->withMaxRedirects(10)
            ->withMiddleware(['logging'])
            ->withUserAgent('Four-MarketplaceHttp/1.0 (Development)');
    }

    /**
     * Configure for production with strict settings
     */
    public function forProduction(): self
    {
        return $this
            ->withTimeout(30.0)
            ->withMaxRedirects(3)
            ->withMiddleware(['logging', 'rate_limiting', 'retry', 'performance'])
            ->withUserAgent('Four-MarketplaceHttp/1.0 (Production)');
    }

    /**
     * Build the final ClientConfig instance
     */
    public function build(): ClientConfig
    {
        return new ClientConfig(
            $this->baseUri,
            $this->defaultHeaders,
            $this->middleware,
            $this->authProvider,
            $this->rateLimiterFactory,
            $this->logger,
            $this->cache,
            $this->retryConfig,
            $this->timeout,
            $this->maxRedirects,
            $this->additionalOptions
        );
    }
}