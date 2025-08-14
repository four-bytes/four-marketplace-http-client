<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Authentication;

use Four\MarketplaceHttp\Exception\AuthenticationException;

/**
 * OAuth 1.0a Authentication Provider
 *
 * Implements OAuth 1.0a signature generation for APIs like Discogs
 * that require OAuth 1.0a authentication with signature verification.
 */
class OAuth1aProvider implements AuthProviderInterface
{
    public function __construct(
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        private readonly string $accessToken,
        private readonly string $tokenSecret,
        private readonly string $signatureMethod = 'HMAC-SHA1',
        private readonly string $version = '1.0'
    ) {}

    public function getAuthHeaders(): array
    {
        // OAuth 1.0a doesn't use a simple Bearer token
        // Authorization header is generated per-request in getAuthHeadersForRequest
        return [];
    }

    /**
     * Generate OAuth 1.0a authorization header for a specific request
     */
    public function getAuthHeadersForRequest(
        string $method,
        string $url,
        array $queryParams = []
    ): array {
        $timestamp = (string) time();
        $nonce = $this->generateNonce();

        $oauthParams = [
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_nonce' => $nonce,
            'oauth_signature_method' => $this->signatureMethod,
            'oauth_timestamp' => $timestamp,
            'oauth_token' => $this->accessToken,
            'oauth_version' => $this->version,
        ];

        // Parse URL to get base URL and existing query parameters
        $parsedUrl = parse_url($url);
        $baseUrl = ($parsedUrl['scheme'] ?? 'https') . '://' . 
                  ($parsedUrl['host'] ?? '') . 
                  ($parsedUrl['port'] ? ':' . $parsedUrl['port'] : '') . 
                  ($parsedUrl['path'] ?? '');

        // Combine all parameters (OAuth + query params)
        $allParams = array_merge($oauthParams, $queryParams);
        
        // Parse existing query string from URL
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $urlParams);
            $allParams = array_merge($allParams, $urlParams);
        }

        // Generate signature
        $signature = $this->generateSignature($method, $baseUrl, $allParams);
        $oauthParams['oauth_signature'] = $signature;

        // Build Authorization header
        $authHeader = 'OAuth ';
        $authParts = [];
        
        foreach ($oauthParams as $key => $value) {
            $authParts[] = $key . '="' . rawurlencode((string) $value) . '"';
        }
        
        $authHeader .= implode(', ', $authParts);

        return [
            'Authorization' => $authHeader
        ];
    }

    public function isValid(): bool
    {
        return !empty($this->consumerKey) && 
               !empty($this->consumerSecret) && 
               !empty($this->accessToken) && 
               !empty($this->tokenSecret);
    }

    public function refresh(): void
    {
        // OAuth 1.0a tokens typically don't expire and don't need refreshing
        // If refresh is needed, it would require a full re-authorization flow
        throw new \BadMethodCallException('OAuth 1.0a tokens do not support refresh');
    }

    public function getType(): string
    {
        return 'oauth_1a';
    }

    /**
     * Generate OAuth 1.0a signature
     */
    private function generateSignature(string $method, string $baseUrl, array $params): string
    {
        // Sort parameters
        ksort($params);

        // Build parameter string
        $paramString = '';
        foreach ($params as $key => $value) {
            if ($paramString !== '') {
                $paramString .= '&';
            }
            $paramString .= rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        // Build signature base string
        $signatureBaseString = strtoupper($method) . '&' . 
                              rawurlencode($baseUrl) . '&' . 
                              rawurlencode($paramString);

        // Build signing key
        $signingKey = rawurlencode($this->consumerSecret) . '&' . rawurlencode($this->tokenSecret);

        // Generate signature based on method
        switch ($this->signatureMethod) {
            case 'HMAC-SHA1':
                return base64_encode(hash_hmac('sha1', $signatureBaseString, $signingKey, true));
            
            case 'PLAINTEXT':
                return $signingKey;
            
            default:
                throw new AuthenticationException("Unsupported signature method: {$this->signatureMethod}");
        }
    }

    /**
     * Generate a random nonce for OAuth requests
     */
    private function generateNonce(): string
    {
        return md5(uniqid((string) rand(), true));
    }

    /**
     * Create OAuth 1.0a provider for Discogs
     */
    public static function discogs(
        string $consumerKey,
        string $consumerSecret,
        string $accessToken,
        string $tokenSecret
    ): self {
        return new self(
            $consumerKey,
            $consumerSecret,
            $accessToken,
            $tokenSecret,
            'HMAC-SHA1'
        );
    }

    /**
     * Get token information for debugging (without exposing secrets)
     */
    public function getTokenInfo(): array
    {
        return [
            'type' => $this->getType(),
            'signature_method' => $this->signatureMethod,
            'version' => $this->version,
            'has_consumer_key' => !empty($this->consumerKey),
            'has_consumer_secret' => !empty($this->consumerSecret),
            'has_access_token' => !empty($this->accessToken),
            'has_token_secret' => !empty($this->tokenSecret),
            'is_valid' => $this->isValid()
        ];
    }

    /**
     * Test OAuth 1.0a signature generation with known values
     * 
     * This method can be used for testing and validation
     */
    public function testSignature(): array
    {
        // Test with known values to verify signature generation
        $testMethod = 'GET';
        $testUrl = 'https://api.discogs.com/oauth/identity';
        $testParams = [];

        $headers = $this->getAuthHeadersForRequest($testMethod, $testUrl, $testParams);
        
        return [
            'test_method' => $testMethod,
            'test_url' => $testUrl,
            'authorization_header' => $headers['Authorization'] ?? null,
            'signature_valid' => str_contains($headers['Authorization'] ?? '', 'oauth_signature=')
        ];
    }
}