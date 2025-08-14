<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Factory;

use Four\MarketplaceHttp\Configuration\ClientConfig;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Factory interface for creating HTTP clients with marketplace-specific configurations
 *
 * This interface defines the contract for creating HTTP clients that are optimized
 * for marketplace API integrations, including pre-configured rate limiting,
 * authentication, and retry strategies.
 */
interface HttpClientFactoryInterface
{
    /**
     * Create a generic HTTP client with custom configuration
     */
    public function createClient(ClientConfig $config): HttpClientInterface;

    /**
     * Create an Amazon SP-API HTTP client with optimized configuration
     */
    public function createAmazonClient(ClientConfig $config): HttpClientInterface;

    /**
     * Create an eBay API HTTP client with optimized configuration
     */
    public function createEbayClient(ClientConfig $config): HttpClientInterface;

    /**
     * Create a Discogs API HTTP client with optimized configuration
     */
    public function createDiscogsClient(ClientConfig $config): HttpClientInterface;

    /**
     * Create a Bandcamp HTTP client with optimized configuration
     */
    public function createBandcampClient(ClientConfig $config): HttpClientInterface;

    /**
     * Get available middleware types for the factory
     *
     * @return string[]
     */
    public function getAvailableMiddleware(): array;
}