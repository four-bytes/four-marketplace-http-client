<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Authentication;

use Four\MarketplaceHttp\Exception\AuthenticationException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Authentication provider for OAuth 2.0 and OAuth 1.0a flows
 *
 * Handles OAuth token management, refresh, and header generation
 * for marketplace APIs that use OAuth authentication.
 */
class OAuthProvider implements AuthProviderInterface
{
    private ?string $accessToken = null;
    private ?\DateTimeInterface $expiresAt = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $tokenEndpoint,
        private readonly ?string $refreshToken = null,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly array $scopes = [],
        private readonly string $oauthVersion = '2.0'
    ) {}

    public function getAuthHeaders(): array
    {
        if (!$this->isValid()) {
            $this->refresh();
        }

        if ($this->accessToken === null) {
            throw AuthenticationException::missing();
        }

        return [
            'Authorization' => "Bearer {$this->accessToken}"
        ];
    }

    public function isValid(): bool
    {
        if ($this->accessToken === null) {
            return false;
        }

        if ($this->expiresAt !== null) {
            // Add 30 seconds buffer to avoid edge cases
            $bufferTime = (new \DateTimeImmutable())->add(new \DateInterval('PT30S'));
            return $this->expiresAt > $bufferTime;
        }

        return true;
    }

    public function refresh(): void
    {
        if ($this->oauthVersion === '2.0') {
            $this->refreshOAuth2Token();
        } else {
            throw new \BadMethodCallException('OAuth 1.0a refresh not implemented');
        }
    }

    public function getType(): string
    {
        return "oauth_{$this->oauthVersion}";
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    /**
     * Set access token directly (for when you have a valid token)
     */
    public function setAccessToken(string $accessToken, ?\DateTimeInterface $expiresAt = null): void
    {
        $this->accessToken = $accessToken;
        $this->expiresAt = $expiresAt;
    }

    /**
     * Refresh OAuth 2.0 token using refresh token or client credentials
     */
    private function refreshOAuth2Token(): void
    {
        $grantType = $this->refreshToken ? 'refresh_token' : 'client_credentials';
        
        $params = [
            'grant_type' => $grantType,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ];

        if ($this->refreshToken) {
            $params['refresh_token'] = $this->refreshToken;
        }

        if (!empty($this->scopes)) {
            $params['scope'] = implode(' ', $this->scopes);
        }

        $body = $this->streamFactory->createStream(http_build_query($params));
        
        $request = $this->requestFactory
            ->createRequest('POST', $this->tokenEndpoint)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body);

        try {
            $response = $this->httpClient->sendRequest($request);
            
            if ($response->getStatusCode() !== 200) {
                throw AuthenticationException::invalidCredentials();
            }

            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            
            if (!isset($data['access_token'])) {
                throw AuthenticationException::invalidCredentials();
            }

            $this->accessToken = $data['access_token'];
            
            if (isset($data['expires_in'])) {
                $expiresIn = (int) $data['expires_in'];
                $this->expiresAt = (new \DateTimeImmutable())->add(new \DateInterval("PT{$expiresIn}S"));
            }

        } catch (\JsonException $e) {
            throw new AuthenticationException('Invalid JSON response from token endpoint', 0, $e);
        } catch (\Exception $e) {
            throw new AuthenticationException('Failed to refresh OAuth token: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create provider for Amazon SP-API LWA OAuth
     */
    public static function amazon(
        string $clientId,
        string $clientSecret,
        ?string $refreshToken = null,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        array $scopes = []
    ): self {
        return new self(
            $clientId,
            $clientSecret,
            'https://api.amazon.com/auth/o2/token',
            $refreshToken,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $scopes
        );
    }

    /**
     * Create provider for eBay OAuth
     */
    public static function ebay(
        string $clientId,
        string $clientSecret,
        ?string $refreshToken = null,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        array $scopes = [],
        bool $sandbox = false
    ): self {
        $tokenEndpoint = $sandbox 
            ? 'https://api.sandbox.ebay.com/identity/v1/oauth2/token'
            : 'https://api.ebay.com/identity/v1/oauth2/token';

        return new self(
            $clientId,
            $clientSecret,
            $tokenEndpoint,
            $refreshToken,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $scopes
        );
    }

    /**
     * Get token information for debugging
     */
    public function getTokenInfo(): array
    {
        return [
            'type' => $this->getType(),
            'has_access_token' => $this->accessToken !== null,
            'has_refresh_token' => $this->refreshToken !== null,
            'expires_at' => $this->expiresAt?->format('c'),
            'is_valid' => $this->isValid(),
            'scopes' => $this->scopes
        ];
    }
}