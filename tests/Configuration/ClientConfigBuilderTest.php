<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Tests\Configuration;

use Four\MarketplaceHttp\Configuration\ClientConfig;
use Four\MarketplaceHttp\Tests\TestCase;

/**
 * Tests for ClientConfigBuilder fluent API
 */
class ClientConfigBuilderTest extends TestCase
{
    public function testBasicConfiguration(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withTimeout(30.0)
            ->withMaxRedirects(5)
            ->build();
        
        $this->assertSame('https://api.example.com', $config->baseUri);
        $this->assertSame(30.0, $config->timeout);
        $this->assertSame(5, $config->maxRedirects);
    }
    
    public function testWithHeaders(): void
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'Test/1.0'
        ];
        
        $config = ClientConfig::create('https://api.example.com')
            ->withHeaders($headers)
            ->build();
        
        $this->assertSame($headers['Accept'], $config->defaultHeaders['Accept']);
        $this->assertSame($headers['User-Agent'], $config->defaultHeaders['User-Agent']);
    }
    
    public function testWithSingleHeader(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withHeader('Authorization', 'Bearer test-token')
            ->build();
        
        $this->assertSame('Bearer test-token', $config->defaultHeaders['Authorization']);
    }
    
    public function testWithUserAgent(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withUserAgent('MyApp/2.0')
            ->build();
        
        $this->assertSame('MyApp/2.0', $config->defaultHeaders['User-Agent']);
    }
    
    public function testWithAccept(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withAccept('application/xml')
            ->build();
        
        $this->assertSame('application/xml', $config->defaultHeaders['Accept']);
    }
    
    public function testWithContentType(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withContentType('application/json')
            ->build();
        
        $this->assertSame('application/json', $config->defaultHeaders['Content-Type']);
    }
    
    public function testWithMiddleware(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withMiddleware(['logging', 'rate_limiting'])
            ->build();
        
        $this->assertContains('logging', $config->middleware);
        $this->assertContains('rate_limiting', $config->middleware);
        $this->assertCount(2, $config->middleware);
    }
    
    public function testWithSingleMiddleware(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withMiddleware('logging')
            ->withMiddleware('retry')
            ->build();
        
        $this->assertContains('logging', $config->middleware);
        $this->assertContains('retry', $config->middleware);
        $this->assertCount(2, $config->middleware);
    }
    
    public function testWithLogging(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withLogging($this->logger)
            ->build();
        
        $this->assertContains('logging', $config->middleware);
        $this->assertSame($this->logger, $config->logger);
    }
    
    public function testWithRetries(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withRetries(5, [500, 502, 503])
            ->build();
        
        $this->assertContains('retry', $config->middleware);
        $this->assertNotNull($config->retryConfig);
        $this->assertSame(5, $config->retryConfig->getMaxAttempts());
        $this->assertSame([500, 502, 503], $config->retryConfig->getRetryableStatusCodes());
    }
    
    public function testWithAuth(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withAuth('bearer', 'test-token')
            ->build();
        
        $this->assertSame('Bearer test-token', $config->defaultHeaders['Authorization']);
    }
    
    public function testWithAuthBasic(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withAuth('basic', 'user:pass')
            ->build();
        
        $expected = 'Basic ' . base64_encode('user:pass');
        $this->assertSame($expected, $config->defaultHeaders['Authorization']);
    }
    
    public function testWithAuthApiKey(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withAuth('api_key', 'my-api-key')
            ->build();
        
        $this->assertSame('my-api-key', $config->defaultHeaders['Authorization']);
    }
    
    public function testWithAuthToken(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withAuth('token', 'my-token')
            ->build();
        
        $this->assertSame('Token my-token', $config->defaultHeaders['Authorization']);
    }
    
    public function testWithAuthInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported auth type: invalid');
        
        ClientConfig::create('https://api.example.com')
            ->withAuth('invalid', 'token')
            ->build();
    }
    
    public function testForAmazon(): void
    {
        $config = ClientConfig::create('https://sellingpartnerapi-eu.amazon.com')
            ->forAmazon()
            ->build();
        
        $this->assertSame('application/json', $config->defaultHeaders['Accept']);
        $this->assertSame('application/json', $config->defaultHeaders['Content-Type']);
        $this->assertStringContains('Amazon SP-API', $config->defaultHeaders['User-Agent']);
        $this->assertSame(30.0, $config->timeout);
        $this->assertContains('logging', $config->middleware);
        $this->assertContains('rate_limiting', $config->middleware);
        $this->assertContains('retry', $config->middleware);
    }
    
    public function testForEbay(): void
    {
        $config = ClientConfig::create('https://api.ebay.com')
            ->forEbay()
            ->build();
        
        $this->assertSame('application/json', $config->defaultHeaders['Accept']);
        $this->assertSame('application/json', $config->defaultHeaders['Content-Type']);
        $this->assertStringContains('eBay API', $config->defaultHeaders['User-Agent']);
        $this->assertSame(25.0, $config->timeout);
        $this->assertContains('logging', $config->middleware);
        $this->assertContains('rate_limiting', $config->middleware);
        $this->assertContains('retry', $config->middleware);
    }
    
    public function testForDiscogs(): void
    {
        $config = ClientConfig::create('https://api.discogs.com')
            ->forDiscogs()
            ->build();
        
        $this->assertSame('application/vnd.discogs.v2.discogs+json', $config->defaultHeaders['Accept']);
        $this->assertStringContains('4bytes.de', $config->defaultHeaders['User-Agent']);
        $this->assertSame(15.0, $config->timeout);
        $this->assertContains('logging', $config->middleware);
        $this->assertContains('rate_limiting', $config->middleware);
        $this->assertContains('oauth_1a', $config->middleware);
    }
    
    public function testForBandcamp(): void
    {
        $config = ClientConfig::create('https://bandcamp.com/api')
            ->forBandcamp()
            ->build();
        
        $this->assertSame('application/json', $config->defaultHeaders['Accept']);
        $this->assertStringContains('Mozilla/5.0', $config->defaultHeaders['User-Agent']);
        $this->assertSame(15.0, $config->timeout);
        $this->assertContains('logging', $config->middleware);
        $this->assertContains('rate_limiting', $config->middleware);
    }
    
    public function testForDevelopment(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->forDevelopment()
            ->build();
        
        $this->assertSame(60.0, $config->timeout);
        $this->assertSame(10, $config->maxRedirects);
        $this->assertContains('logging', $config->middleware);
        $this->assertStringContains('Development', $config->defaultHeaders['User-Agent']);
    }
    
    public function testForProduction(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->forProduction()
            ->build();
        
        $this->assertSame(30.0, $config->timeout);
        $this->assertSame(3, $config->maxRedirects);
        $this->assertContains('logging', $config->middleware);
        $this->assertContains('rate_limiting', $config->middleware);
        $this->assertContains('retry', $config->middleware);
        $this->assertContains('performance', $config->middleware);
        $this->assertStringContains('Production', $config->defaultHeaders['User-Agent']);
    }
    
    public function testChainedConfiguration(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withTimeout(45.0)
            ->withUserAgent('ChainedTest/1.0')
            ->withAuth('bearer', 'test-token')
            ->withMiddleware(['logging', 'retry'])
            ->withMaxRedirects(2)
            ->build();
        
        $this->assertSame('https://api.example.com', $config->baseUri);
        $this->assertSame(45.0, $config->timeout);
        $this->assertSame('ChainedTest/1.0', $config->defaultHeaders['User-Agent']);
        $this->assertSame('Bearer test-token', $config->defaultHeaders['Authorization']);
        $this->assertContains('logging', $config->middleware);
        $this->assertContains('retry', $config->middleware);
        $this->assertSame(2, $config->maxRedirects);
    }
    
    public function testInvalidTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be greater than 0');
        
        ClientConfig::create('https://api.example.com')
            ->withTimeout(0.0)
            ->build();
    }
    
    public function testInvalidMaxRedirects(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Max redirects must be non-negative');
        
        ClientConfig::create('https://api.example.com')
            ->withMaxRedirects(-1)
            ->build();
    }
    
    public function testWithOptions(): void
    {
        $options = [
            'verify_peer' => false,
            'verify_host' => false
        ];
        
        $config = ClientConfig::create('https://api.example.com')
            ->withOptions($options)
            ->build();
        
        $this->assertSame(false, $config->additionalOptions['verify_peer']);
        $this->assertSame(false, $config->additionalOptions['verify_host']);
    }
}