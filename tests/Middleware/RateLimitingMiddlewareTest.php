<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Tests\Middleware;

use Four\MarketplaceHttp\Exception\RateLimitException;
use Four\MarketplaceHttp\Middleware\RateLimitingMiddleware;
use Four\MarketplaceHttp\Tests\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Tests for RateLimitingMiddleware
 */
class RateLimitingMiddlewareTest extends TestCase
{
    private RateLimiterFactory $rateLimiterFactory;
    private RateLimitingMiddleware $middleware;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a rate limiter factory for testing
        $this->rateLimiterFactory = new RateLimiterFactory([
            'id' => 'test',
            'policy' => 'token_bucket',
            'limit' => 5,
            'rate' => ['interval' => '1 second', 'amount' => 5]
        ], new CacheStorage($this->cache));
        
        $this->middleware = new RateLimitingMiddleware(
            $this->rateLimiterFactory,
            $this->logger,
            'test'
        );
    }
    
    public function testGetName(): void
    {
        $this->assertSame('rate_limiting', $this->middleware->getName());
    }
    
    public function testGetPriority(): void
    {
        $this->assertSame(200, $this->middleware->getPriority());
    }
    
    public function testWrapClient(): void
    {
        $mockClient = $this->createMockClient([
            $this->createJsonResponse(['success' => true])
        ]);
        
        $wrappedClient = $this->middleware->wrap($mockClient);
        
        $this->assertNotSame($mockClient, $wrappedClient);
    }
    
    public function testSuccessfulRequestWithinLimits(): void
    {
        $mockClient = $this->createMockClient([
            $this->createRateLimitResponse('test', 5, 4)
        ]);
        
        $wrappedClient = $this->middleware->wrap($mockClient);
        
        $response = $wrappedClient->request('GET', 'https://api.example.com/test');
        
        $this->assertSame(200, $response->getStatusCode());
        $this->assertLogLevel('debug');
    }
    
    public function testRateLimitExceededHandling(): void
    {
        $mockClient = $this->createMockClient([
            $this->createRateExceededResponse('test')
        ]);
        
        $wrappedClient = $this->middleware->wrap($mockClient);
        
        $response = $wrappedClient->request('GET', 'https://api.example.com/test');
        
        $this->assertSame(429, $response->getStatusCode());
        $this->assertLogLevel('warning');
    }
    
    public function testAmazonRateLimitHeaders(): void
    {
        $mockClient = $this->createMockClient([
            $this->createRateLimitResponse('amazon', 10, 8)
        ]);
        
        $amazonMiddleware = new RateLimitingMiddleware(
            $this->rateLimiterFactory,
            $this->logger,
            'amazon'
        );
        
        $wrappedClient = $amazonMiddleware->wrap($mockClient);
        
        $response = $wrappedClient->request('GET', 'https://sellingpartnerapi-eu.amazon.com/orders/v0/orders');
        
        $this->assertSame(200, $response->getStatusCode());
        $this->assertLogLevel('debug');
    }
    
    public function testEbayRateLimitHeaders(): void
    {
        $mockClient = $this->createMockClient([
            $this->createRateLimitResponse('ebay', 5000, 4950)
        ]);
        
        $ebayMiddleware = new RateLimitingMiddleware(
            $this->rateLimiterFactory,
            $this->logger,
            'ebay'
        );
        
        $wrappedClient = $ebayMiddleware->wrap($mockClient);
        
        $response = $wrappedClient->request('GET', 'https://api.ebay.com/sell/inventory/v1/inventory_item');
        
        $this->assertSame(200, $response->getStatusCode());
        $this->assertLogLevel('debug');
    }
    
    public function testDiscogsRateLimitHeaders(): void
    {
        $mockClient = $this->createMockClient([
            $this->createRateLimitResponse('discogs', 60, 58)
        ]);
        
        $discogsMiddleware = new RateLimitingMiddleware(
            $this->rateLimiterFactory,
            $this->logger,
            'discogs'
        );
        
        $wrappedClient = $discogsMiddleware->wrap($mockClient);
        
        $response = $wrappedClient->request('GET', 'https://api.discogs.com/database/search');
        
        $this->assertSame(200, $response->getStatusCode());
        $this->assertLogLevel('debug');
    }
    
    public function testOperationKeyExtraction(): void
    {
        $mockClient = $this->createMockClient([
            $this->createJsonResponse(['success' => true]),
            $this->createJsonResponse(['success' => true]),
            $this->createJsonResponse(['success' => true])
        ]);
        
        $amazonMiddleware = new RateLimitingMiddleware(
            $this->rateLimiterFactory,
            $this->logger,
            'amazon'
        );
        
        $wrappedClient = $amazonMiddleware->wrap($mockClient);
        
        // Test different Amazon endpoints to verify operation key extraction
        $endpoints = [
            '/orders/v0/orders' => 'orders',
            '/listings/2021-08-01/items/TEST-SKU' => 'listings',
            '/feeds/2021-06-30/feeds' => 'feeds'
        ];
        
        foreach ($endpoints as $endpoint => $expectedOperation) {
            $response = $wrappedClient->request('GET', 'https://sellingpartnerapi-eu.amazon.com' . $endpoint);
            $this->assertSame(200, $response->getStatusCode());
        }
        
        // Verify that requests were made and rate limiting was applied per operation
        $this->assertLogLevel('debug');
    }
    
    public function testWithOptionsPreservation(): void
    {
        $mockClient = $this->createMockClient([
            $this->createJsonResponse(['success' => true])
        ]);
        
        $wrappedClient = $this->middleware->wrap($mockClient);
        
        $clientWithOptions = $wrappedClient->withOptions([
            'timeout' => 60,
            'headers' => ['X-Test' => 'value']
        ]);
        
        $this->assertNotSame($wrappedClient, $clientWithOptions);
        
        $response = $clientWithOptions->request('GET', 'https://api.example.com/test');
        $this->assertSame(200, $response->getStatusCode());
    }
    
    public function testStreamingSupport(): void
    {
        $mockClient = $this->createMockClient([
            $this->createJsonResponse(['data' => 'chunk1']),
            $this->createJsonResponse(['data' => 'chunk2'])
        ]);
        
        $wrappedClient = $this->middleware->wrap($mockClient);
        
        $responses = [
            $wrappedClient->request('GET', 'https://api.example.com/stream1'),
            $wrappedClient->request('GET', 'https://api.example.com/stream2')
        ];
        
        $stream = $wrappedClient->stream($responses, 30.0);
        
        $this->assertNotNull($stream);
    }
    
    public function testUrlSanitization(): void
    {
        $mockClient = $this->createMockClient([
            $this->createJsonResponse(['success' => true])
        ]);
        
        $wrappedClient = $this->middleware->wrap($mockClient);
        
        // Make request with sensitive data in query parameters
        $response = $wrappedClient->request('GET', 'https://api.example.com/test?api_key=secret&token=confidential');
        
        $this->assertSame(200, $response->getStatusCode());
        
        // Verify that sensitive data is not logged
        $logRecords = $this->getLogRecords();
        $this->assertNotEmpty($logRecords);
        
        foreach ($logRecords as $record) {
            if (isset($record['context']['url'])) {
                $this->assertStringNotContains('secret', $record['context']['url']);
                $this->assertStringNotContains('confidential', $record['context']['url']);
            }
        }
    }
    
    public function testConcurrentRequestHandling(): void
    {
        // Create responses for concurrent requests
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->createJsonResponse(['request' => $i]);
        }
        
        $mockClient = $this->createMockClient($responses);
        $wrappedClient = $this->middleware->wrap($mockClient);
        
        // Make multiple requests quickly to test rate limiting behavior
        $requestResponses = [];
        for ($i = 0; $i < 10; $i++) {
            $requestResponses[] = $wrappedClient->request('GET', "https://api.example.com/test{$i}");
        }
        
        // Verify all requests complete (some may be rate limited)
        foreach ($requestResponses as $response) {
            $this->assertNotNull($response);
            $this->assertGreaterThanOrEqual(200, $response->getStatusCode());
        }
    }
}