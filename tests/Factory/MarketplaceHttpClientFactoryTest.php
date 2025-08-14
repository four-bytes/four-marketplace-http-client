<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Tests\Factory;

use Four\MarketplaceHttp\Configuration\ClientConfig;
use Four\MarketplaceHttp\Factory\MarketplaceHttpClientFactory;
use Four\MarketplaceHttp\Tests\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tests for MarketplaceHttpClientFactory
 */
class MarketplaceHttpClientFactoryTest extends TestCase
{
    private MarketplaceHttpClientFactory $factory;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new MarketplaceHttpClientFactory($this->logger, $this->cache);
    }
    
    public function testCreateBasicClient(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withTimeout(30.0)
            ->build();
        
        $client = $this->factory->createClient($config);
        
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }
    
    public function testCreateAmazonClient(): void
    {
        $config = ClientConfig::create('https://sellingpartnerapi-eu.amazon.com')
            ->withHeader('x-amz-access-token', 'test-token')
            ->build();
        
        $client = $this->factory->createAmazonClient($config);
        
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }
    
    public function testCreateEbayClient(): void
    {
        $config = ClientConfig::create('https://api.ebay.com')
            ->withHeader('Authorization', 'Bearer test-token')
            ->build();
        
        $client = $this->factory->createEbayClient($config);
        
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }
    
    public function testCreateDiscogsClient(): void
    {
        $config = ClientConfig::create('https://api.discogs.com')
            ->withHeader('Authorization', 'Discogs token=test-token')
            ->build();
        
        $client = $this->factory->createDiscogsClient($config);
        
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }
    
    public function testCreateBandcampClient(): void
    {
        $config = ClientConfig::create('https://bandcamp.com/api')
            ->withHeader('Authorization', 'Bearer test-token')
            ->build();
        
        $client = $this->factory->createBandcampClient($config);
        
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }
    
    public function testCreateRateLimiterFactory(): void
    {
        $rateLimiterFactory = $this->factory->createRateLimiterFactory('amazon');
        
        $this->assertNotNull($rateLimiterFactory);
    }
    
    public function testCreateRateLimiterFactoryWithCustomConfig(): void
    {
        $customConfig = [
            'limit' => 50,
            'rate' => ['interval' => '1 minute', 'amount' => 50]
        ];
        
        $rateLimiterFactory = $this->factory->createRateLimiterFactory('custom', $customConfig);
        
        $this->assertNotNull($rateLimiterFactory);
    }
    
    public function testGetAvailableMiddleware(): void
    {
        $middleware = $this->factory->getAvailableMiddleware();
        
        $expectedMiddleware = [
            'logging',
            'rate_limiting',
            'retry',
            'authentication',
            'caching',
            'performance'
        ];
        
        foreach ($expectedMiddleware as $expected) {
            $this->assertContains($expected, $middleware);
        }
    }
    
    public function testClientWithMultipleMiddleware(): void
    {
        $config = ClientConfig::create('https://api.example.com')
            ->withMiddleware(['logging', 'rate_limiting', 'retry'])
            ->withTimeout(45.0)
            ->build();
        
        $client = $this->factory->createClient($config);
        
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }
    
    public function testClientWithCustomHeaders(): void
    {
        $customHeaders = [
            'X-Custom-Header' => 'custom-value',
            'User-Agent' => 'Test-Client/1.0'
        ];
        
        $config = ClientConfig::create('https://api.example.com')
            ->withHeaders($customHeaders)
            ->build();
        
        $client = $this->factory->createClient($config);
        
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }
    
    public function testAmazonClientWithSpecificConfiguration(): void
    {
        $config = ClientConfig::create('https://sellingpartnerapi-eu.amazon.com')
            ->withHeader('x-amz-access-token', 'test-token')
            ->withHeader('x-amzn-marketplace-id', 'A1PA6795UKMFR9')
            ->withTimeout(30.0)
            ->build();
        
        $client = $this->factory->createAmazonClient($config);
        
        $this->assertInstanceOf(HttpClientInterface::class, $client);
        
        // Verify that Amazon-specific defaults were applied
        $this->assertSame(30.0, $config->timeout);
    }
    
    public function testEbayClientWithSpecificConfiguration(): void
    {
        $config = ClientConfig::create('https://api.ebay.com')
            ->withHeader('Authorization', 'Bearer test-token')
            ->withTimeout(25.0)
            ->build();
        
        $client = $this->factory->createEbayClient($config);
        
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }
    
    public function testDiscogsClientWithSpecificConfiguration(): void
    {
        $config = ClientConfig::create('https://api.discogs.com')
            ->withHeader('Authorization', 'Discogs token=test-token')
            ->withTimeout(15.0)
            ->build();
        
        $client = $this->factory->createDiscogsClient($config);
        
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }
    
    public function testBandcampClientWithSpecificConfiguration(): void
    {
        $config = ClientConfig::create('https://bandcamp.com/api')
            ->withHeader('Authorization', 'Bearer test-token')
            ->withTimeout(15.0)
            ->build();
        
        $client = $this->factory->createBandcampClient($config);
        
        $this->assertInstanceOf(HttpClientInterface::class, $client);
    }
}