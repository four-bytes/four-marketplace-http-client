<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Middleware;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Interface for HTTP client middleware
 *
 * Middleware components can wrap HttpClient instances to add functionality
 * like logging, rate limiting, caching, authentication, and monitoring.
 */
interface MiddlewareInterface
{
    /**
     * Wrap an HTTP client with middleware functionality
     */
    public function wrap(HttpClientInterface $client): HttpClientInterface;

    /**
     * Get the name of this middleware
     */
    public function getName(): string;

    /**
     * Get the priority of this middleware (higher numbers are applied first)
     */
    public function getPriority(): int;
}