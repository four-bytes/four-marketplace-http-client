<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Client;

use Four\MarketplaceHttp\Configuration\ClientConfig;
use Four\MarketplaceHttp\Factory\HttpClientFactoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Base marketplace client with common functionality
 *
 * Provides a convenient wrapper around the HTTP client with marketplace-specific
 * methods and error handling.
 */
abstract class MarketplaceClient
{
    protected readonly HttpClientInterface $httpClient;

    public function __construct(
        protected readonly ClientConfig $config,
        HttpClientFactoryInterface $factory
    ) {
        $this->httpClient = $this->createHttpClient($factory);
    }

    /**
     * Get the marketplace name
     */
    abstract public function getMarketplace(): string;

    /**
     * Create the HTTP client for this marketplace
     */
    abstract protected function createHttpClient(HttpClientFactoryInterface $factory): HttpClientInterface;

    /**
     * Make a GET request
     */
    public function get(string $path, array $query = [], array $headers = []): ResponseInterface
    {
        $options = ['query' => $query];
        
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }

        return $this->httpClient->request('GET', $path, $options);
    }

    /**
     * Make a POST request
     */
    public function post(string $path, mixed $data = null, array $headers = []): ResponseInterface
    {
        $options = [];
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $options['json'] = $data;
            } else {
                $options['body'] = $data;
            }
        }
        
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }

        return $this->httpClient->request('POST', $path, $options);
    }

    /**
     * Make a PUT request
     */
    public function put(string $path, mixed $data = null, array $headers = []): ResponseInterface
    {
        $options = [];
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $options['json'] = $data;
            } else {
                $options['body'] = $data;
            }
        }
        
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }

        return $this->httpClient->request('PUT', $path, $options);
    }

    /**
     * Make a DELETE request
     */
    public function delete(string $path, array $query = [], array $headers = []): ResponseInterface
    {
        $options = ['query' => $query];
        
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }

        return $this->httpClient->request('DELETE', $path, $options);
    }

    /**
     * Make a PATCH request
     */
    public function patch(string $path, mixed $data = null, array $headers = []): ResponseInterface
    {
        $options = [];
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $options['json'] = $data;
            } else {
                $options['body'] = $data;
            }
        }
        
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }

        return $this->httpClient->request('PATCH', $path, $options);
    }

    /**
     * Get the underlying HTTP client
     */
    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }

    /**
     * Get the client configuration
     */
    public function getConfig(): ClientConfig
    {
        return $this->config;
    }

    /**
     * Parse JSON response safely
     *
     * @return mixed
     */
    protected function parseJsonResponse(ResponseInterface $response): mixed
    {
        try {
            return $response->toArray();
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to parse JSON response: {$e->getMessage()}",
                $response->getStatusCode(),
                $e
            );
        }
    }

    /**
     * Check if response is successful
     */
    protected function isSuccessful(ResponseInterface $response): bool
    {
        $statusCode = $response->getStatusCode();
        return $statusCode >= 200 && $statusCode < 300;
    }
}