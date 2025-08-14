<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Authentication;

/**
 * Interface for authentication providers
 *
 * Authentication providers are responsible for managing authentication
 * credentials and generating appropriate headers for API requests.
 */
interface AuthProviderInterface
{
    /**
     * Get authentication headers for HTTP requests
     *
     * @return array<string, string>
     */
    public function getAuthHeaders(): array;

    /**
     * Check if the current authentication is valid
     */
    public function isValid(): bool;

    /**
     * Refresh authentication credentials if possible
     *
     * @throws \Four\MarketplaceHttp\Exception\AuthenticationException
     */
    public function refresh(): void;

    /**
     * Get the authentication type
     */
    public function getType(): string;

    /**
     * Get expiration time of current authentication (if applicable)
     */
    public function getExpiresAt(): ?\DateTimeInterface;
}