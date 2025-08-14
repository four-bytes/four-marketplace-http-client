<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Middleware;

use Four\MarketplaceHttp\Authentication\OAuth1aProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Middleware that adds OAuth 1.0a authentication to requests
 *
 * This middleware intercepts HTTP requests and adds proper OAuth 1.0a
 * authorization headers with signatures calculated per request.
 */
class OAuth1aMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly OAuth1aProvider $authProvider,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {}

    public function wrap(HttpClientInterface $client): HttpClientInterface
    {
        return new OAuth1aHttpClient($client, $this->authProvider, $this->logger);
    }

    public function getName(): string
    {
        return 'oauth_1a';
    }

    public function getPriority(): int
    {
        return 300; // High priority, apply before rate limiting and logging
    }
}

/**
 * HTTP Client decorator that adds OAuth 1.0a authentication
 */
class OAuth1aHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly OAuth1aProvider $authProvider,
        private readonly LoggerInterface $logger
    ) {}

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!$this->authProvider->isValid()) {
            throw new \RuntimeException('OAuth 1.0a provider is not properly configured');
        }

        // Extract query parameters from options for signature calculation
        $queryParams = $options['query'] ?? [];
        
        // Get OAuth 1.0a authorization header for this specific request
        $oauthHeaders = $this->authProvider->getAuthHeadersForRequest(
            $method,
            $url,
            $queryParams
        );

        // Merge OAuth headers with existing headers
        $existingHeaders = $options['headers'] ?? [];
        $mergedHeaders = array_merge($existingHeaders, $oauthHeaders);

        // Update options with OAuth headers
        $options['headers'] = $mergedHeaders;

        $this->logger->debug('Adding OAuth 1.0a authentication', [
            'method' => $method,
            'url' => $this->sanitizeUrl($url),
            'has_oauth_header' => isset($oauthHeaders['Authorization'])
        ]);

        try {
            $response = $this->client->request($method, $url, $options);
            
            $this->logger->debug('OAuth 1.0a request completed', [
                'method' => $method,
                'url' => $this->sanitizeUrl($url),
                'status_code' => $response->getStatusCode()
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('OAuth 1.0a request failed', [
                'method' => $method,
                'url' => $this->sanitizeUrl($url),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
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
            $this->authProvider,
            $this->logger
        );
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
        
        $sanitized = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'unknown');
        
        if (isset($parsed['port'])) {
            $sanitized .= ':' . $parsed['port'];
        }
        
        if (isset($parsed['path'])) {
            $sanitized .= $parsed['path'];
        }
        
        // Exclude query parameters to avoid exposing sensitive data
        return $sanitized;
    }
}