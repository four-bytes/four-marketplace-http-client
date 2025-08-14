<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Authentication;

use Four\MarketplaceHttp\Exception\AuthenticationException;

/**
 * Authentication provider for token-based authentication
 *
 * Handles Bearer tokens, API keys, and other token-based authentication methods.
 */
class TokenProvider implements AuthProviderInterface
{
    public function __construct(
        private string $token,
        private readonly string $headerName = 'Authorization',
        private readonly string $headerPrefix = 'Bearer',
        private readonly ?\DateTimeInterface $expiresAt = null
    ) {}

    public function getAuthHeaders(): array
    {
        if (!$this->isValid()) {
            throw AuthenticationException::tokenExpired();
        }

        $headerValue = $this->headerPrefix ? "{$this->headerPrefix} {$this->token}" : $this->token;

        return [
            $this->headerName => $headerValue
        ];
    }

    public function isValid(): bool
    {
        if (empty($this->token)) {
            return false;
        }

        if ($this->expiresAt !== null) {
            return $this->expiresAt > new \DateTimeImmutable();
        }

        return true;
    }

    public function refresh(): void
    {
        throw new \BadMethodCallException('Token refresh not supported by this provider');
    }

    public function getType(): string
    {
        return 'token';
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    /**
     * Create provider for Bearer token authentication
     */
    public static function bearer(string $token, ?\DateTimeInterface $expiresAt = null): self
    {
        return new self($token, 'Authorization', 'Bearer', $expiresAt);
    }

    /**
     * Create provider for API key authentication
     */
    public static function apiKey(string $token, string $headerName = 'X-API-Key'): self
    {
        return new self($token, $headerName, '');
    }

    /**
     * Create provider for Amazon SP-API LWA token
     */
    public static function amazonLwa(string $lwaToken, ?\DateTimeInterface $expiresAt = null): self
    {
        return new self($lwaToken, 'x-amz-access-token', '', $expiresAt);
    }

    /**
     * Create provider for Discogs token authentication
     */
    public static function discogs(string $token): self
    {
        return new self($token, 'Authorization', 'Discogs token=');
    }

    /**
     * Update the token
     */
    public function updateToken(string $token, ?\DateTimeInterface $expiresAt = null): void
    {
        $this->token = $token;
        
        if ($expiresAt !== null) {
            $this->expiresAt = $expiresAt;
        }
    }

    /**
     * Get current token (for debugging/monitoring)
     */
    public function getTokenInfo(): array
    {
        return [
            'type' => $this->getType(),
            'header' => $this->headerName,
            'prefix' => $this->headerPrefix,
            'expires_at' => $this->expiresAt?->format('c'),
            'is_valid' => $this->isValid(),
            'token_length' => strlen($this->token)
        ];
    }
}