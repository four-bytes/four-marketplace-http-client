<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Tests\Authentication;

use Four\MarketplaceHttp\Authentication\OAuth1aProvider;
use Four\MarketplaceHttp\Tests\TestCase;

/**
 * Tests for OAuth1aProvider
 */
class OAuth1aProviderTest extends TestCase
{
    private OAuth1aProvider $provider;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->provider = new OAuth1aProvider(
            'test-consumer-key',
            'test-consumer-secret',
            'test-access-token',
            'test-token-secret'
        );
    }
    
    public function testGetType(): void
    {
        $this->assertSame('oauth_1a', $this->provider->getType());
    }
    
    public function testIsValidWithCompleteCredentials(): void
    {
        $this->assertTrue($this->provider->isValid());
    }
    
    public function testIsValidWithMissingCredentials(): void
    {
        $provider = new OAuth1aProvider('', '', '', '');
        $this->assertFalse($provider->isValid());
        
        $provider = new OAuth1aProvider('key', '', '', '');
        $this->assertFalse($provider->isValid());
        
        $provider = new OAuth1aProvider('key', 'secret', '', '');
        $this->assertFalse($provider->isValid());
        
        $provider = new OAuth1aProvider('key', 'secret', 'token', '');
        $this->assertFalse($provider->isValid());
    }
    
    public function testGetAuthHeaders(): void
    {
        $headers = $this->provider->getAuthHeaders();
        $this->assertEmpty($headers);
        // OAuth 1.0a doesn't provide static headers, they're generated per request
    }
    
    public function testGetAuthHeadersForRequest(): void
    {
        $method = 'GET';
        $url = 'https://api.discogs.com/oauth/identity';
        $queryParams = ['param1' => 'value1'];
        
        $headers = $this->provider->getAuthHeadersForRequest($method, $url, $queryParams);
        
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringStartsWith('OAuth ', $headers['Authorization']);
        
        // Verify OAuth parameters are present
        $authHeader = $headers['Authorization'];
        $this->assertStringContains('oauth_consumer_key=', $authHeader);
        $this->assertStringContains('oauth_nonce=', $authHeader);
        $this->assertStringContains('oauth_signature_method=', $authHeader);
        $this->assertStringContains('oauth_timestamp=', $authHeader);
        $this->assertStringContains('oauth_token=', $authHeader);
        $this->assertStringContains('oauth_version=', $authHeader);
        $this->assertStringContains('oauth_signature=', $authHeader);
    }
    
    public function testGetAuthHeadersForRequestWithEmptyParams(): void
    {
        $method = 'GET';
        $url = 'https://api.discogs.com/oauth/identity';
        
        $headers = $this->provider->getAuthHeadersForRequest($method, $url);
        
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringStartsWith('OAuth ', $headers['Authorization']);
    }
    
    public function testGetAuthHeadersForPostRequest(): void
    {
        $method = 'POST';
        $url = 'https://api.discogs.com/marketplace/listings';
        $queryParams = ['currency' => 'USD'];
        
        $headers = $this->provider->getAuthHeadersForRequest($method, $url, $queryParams);
        
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringStartsWith('OAuth ', $headers['Authorization']);
        
        // Verify the signature is different for POST
        $getHeaders = $this->provider->getAuthHeadersForRequest('GET', $url, $queryParams);
        $this->assertNotSame($headers['Authorization'], $getHeaders['Authorization']);
    }
    
    public function testGetAuthHeadersForRequestWithUrlParams(): void
    {
        $method = 'GET';
        $url = 'https://api.discogs.com/database/search?type=release&title=test';
        
        $headers = $this->provider->getAuthHeadersForRequest($method, $url);
        
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringStartsWith('OAuth ', $headers['Authorization']);
    }
    
    public function testSignatureGeneration(): void
    {
        $method = 'GET';
        $url = 'https://api.discogs.com/oauth/identity';
        
        // Generate signature twice with same parameters
        $headers1 = $this->provider->getAuthHeadersForRequest($method, $url);
        
        // Small delay to ensure different timestamp/nonce
        usleep(1000);
        
        $headers2 = $this->provider->getAuthHeadersForRequest($method, $url);
        
        // Signatures should be different due to different nonce/timestamp
        $this->assertNotSame($headers1['Authorization'], $headers2['Authorization']);
    }
    
    public function testDiscogsFactor(): void
    {
        $provider = OAuth1aProvider::discogs(
            'discogs-consumer-key',
            'discogs-consumer-secret',
            'discogs-access-token',
            'discogs-token-secret'
        );
        
        $this->assertSame('oauth_1a', $provider->getType());
        $this->assertTrue($provider->isValid());
        
        $headers = $provider->getAuthHeadersForRequest(
            'GET',
            'https://api.discogs.com/oauth/identity'
        );
        
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringContains('oauth_signature_method="HMAC-SHA1"', $headers['Authorization']);
    }
    
    public function testRefreshThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('OAuth 1.0a tokens do not support refresh');
        
        $this->provider->refresh();
    }
    
    public function testGetTokenInfo(): void
    {
        $tokenInfo = $this->provider->getTokenInfo();
        
        $expectedKeys = [
            'type',
            'signature_method',
            'version',
            'has_consumer_key',
            'has_consumer_secret',
            'has_access_token',
            'has_token_secret',
            'is_valid'
        ];
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $tokenInfo);
        }
        
        $this->assertSame('oauth_1a', $tokenInfo['type']);
        $this->assertSame('HMAC-SHA1', $tokenInfo['signature_method']);
        $this->assertSame('1.0', $tokenInfo['version']);
        $this->assertTrue($tokenInfo['has_consumer_key']);
        $this->assertTrue($tokenInfo['has_consumer_secret']);
        $this->assertTrue($tokenInfo['has_access_token']);
        $this->assertTrue($tokenInfo['has_token_secret']);
        $this->assertTrue($tokenInfo['is_valid']);
    }
    
    public function testTestSignature(): void
    {
        $testResult = $this->provider->testSignature();
        
        $this->assertArrayHasKey('test_method', $testResult);
        $this->assertArrayHasKey('test_url', $testResult);
        $this->assertArrayHasKey('authorization_header', $testResult);
        $this->assertArrayHasKey('signature_valid', $testResult);
        
        $this->assertSame('GET', $testResult['test_method']);
        $this->assertSame('https://api.discogs.com/oauth/identity', $testResult['test_url']);
        $this->assertNotNull($testResult['authorization_header']);
        $this->assertTrue($testResult['signature_valid']);
    }
    
    public function testSignatureWithCustomMethod(): void
    {
        $provider = new OAuth1aProvider(
            'test-consumer-key',
            'test-consumer-secret',
            'test-access-token',
            'test-token-secret',
            'PLAINTEXT'
        );
        
        $headers = $provider->getAuthHeadersForRequest(
            'GET',
            'https://api.example.com/test'
        );
        
        $this->assertStringContains('oauth_signature_method="PLAINTEXT"', $headers['Authorization']);
    }
    
    public function testInvalidSignatureMethod(): void
    {
        $provider = new OAuth1aProvider(
            'test-consumer-key',
            'test-consumer-secret',
            'test-access-token',
            'test-token-secret',
            'INVALID-METHOD'
        );
        
        $this->expectException(\Four\MarketplaceHttp\Exception\AuthenticationException::class);
        $this->expectExceptionMessage('Unsupported signature method: INVALID-METHOD');
        
        $provider->getAuthHeadersForRequest('GET', 'https://api.example.com/test');
    }
    
    public function testUrlEncodingInSignature(): void
    {
        $method = 'GET';
        $url = 'https://api.discogs.com/search';
        $queryParams = [
            'q' => 'artist name with spaces',
            'type' => 'release',
            'format' => 'LP+CD' // Contains special characters
        ];
        
        $headers = $this->provider->getAuthHeadersForRequest($method, $url, $queryParams);
        
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringStartsWith('OAuth ', $headers['Authorization']);
        
        // Verify that the signature was generated successfully despite special characters
        $this->assertStringContains('oauth_signature=', $headers['Authorization']);
    }
    
    public function testComplexUrlWithPortAndPath(): void
    {
        $method = 'GET';
        $url = 'https://api.discogs.com:443/database/search?per_page=50';
        
        $headers = $this->provider->getAuthHeadersForRequest($method, $url);
        
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringStartsWith('OAuth ', $headers['Authorization']);
    }
}