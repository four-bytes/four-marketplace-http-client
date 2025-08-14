<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Transport;

/**
 * Transport interface for different HTTP implementations
 *
 * Provides abstraction over different HTTP transport methods
 * including stream_context_create, cURL, and Guzzle.
 */
interface TransportInterface
{
    /**
     * Execute an HTTP request
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $url Request URL
     * @param array<string, mixed> $options Request options (headers, body, timeout, etc.)
     * @return TransportResponse Response object
     * @throws TransportException On request failure
     */
    public function request(string $method, string $url, array $options = []): TransportResponse;

    /**
     * Get transport type identifier
     */
    public function getType(): string;

    /**
     * Check if transport is available on this system
     */
    public function isAvailable(): bool;

    /**
     * Get transport-specific options and capabilities
     *
     * @return array<string, mixed>
     */
    public function getCapabilities(): array;
}