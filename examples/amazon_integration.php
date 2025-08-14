<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Four\MarketplaceHttp\Configuration\ClientConfig;
use Four\MarketplaceHttp\Configuration\RetryConfig;
use Four\MarketplaceHttp\Factory\MarketplaceHttpClientFactory;
use Four\MarketplaceHttp\Authentication\TokenProvider;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Amazon SP-API Integration Example
 *
 * This example demonstrates how to integrate with Amazon SP-API using
 * the four-marketplace-http library with proper authentication,
 * rate limiting, and error handling.
 */

echo "Amazon SP-API Integration Example\n";
echo "=================================\n\n";

// Create logger and cache
$logger = new NullLogger();
$cache = new ArrayAdapter();

// Create factory with dependencies
$factory = new MarketplaceHttpClientFactory($logger, $cache);

// Example 1: Basic Amazon SP-API configuration
echo "1. Creating Amazon SP-API client configuration:\n";

try {
    // Create Amazon-specific rate limiter
    $amazonRateLimiter = $factory->createRateLimiterFactory('amazon', [
        'limit' => 20,
        'rate' => ['interval' => '1 second', 'amount' => 20]
    ]);
    
    // Create authentication provider
    $authProvider = new TokenProvider('Bearer', 'your-lwa-access-token-here');
    
    // Create retry configuration for Amazon
    $retryConfig = RetryConfig::forMarketplace('amazon')
        ->withMaxAttempts(3)
        ->withRetryableStatusCodes([429, 500, 502, 503, 504])
        ->withBackoffStrategy('exponential')
        ->withBaseDelay(1000); // 1 second base delay
    
    // Build the client configuration
    $amazonConfig = ClientConfig::create('https://sellingpartnerapi-eu.amazon.com')
        ->withAuthProvider($authProvider)
        ->withRateLimiterFactory($amazonRateLimiter)
        ->withRetryConfig($retryConfig)
        ->withLogger($logger)
        ->withCache($cache)
        ->withHeaders([
            'x-amz-access-token' => 'your-access-token',
            'x-amzn-marketplace-id' => 'A1PA6795UKMFR9', // Germany
            'Accept' => 'application/json'
        ])
        ->withMiddleware(['logging', 'rate_limiting', 'retry'])
        ->withTimeout(30.0)
        ->build();
    
    // Create the Amazon client
    $amazonClient = $factory->createAmazonClient($amazonConfig);
    
    echo "   ✓ Amazon SP-API client created successfully\n";
    echo "   Rate limiter: " . get_class($amazonRateLimiter) . "\n";
    echo "   Middleware: " . implode(', ', $amazonConfig->middleware) . "\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Failed to create Amazon client: {$e->getMessage()}\n\n";
    exit(1);
}

// Example 2: Fetching orders from Amazon SP-API
echo "2. Fetching orders (mock example):\n";

try {
    // Mock the orders endpoint call
    echo "   Making request to /orders/v0/orders...\n";
    
    // In a real implementation, you would make this call:
    /*
    $response = $amazonClient->request('GET', '/orders/v0/orders', [
        'query' => [
            'MarketplaceIds' => 'A1PA6795UKMFR9',
            'CreatedAfter' => (new DateTime('-7 days'))->format('c'),
            'OrderStatuses' => 'Unshipped'
        ]
    ]);
    
    $ordersData = json_decode($response->getContent(), true);
    echo "   ✓ Orders fetched: " . count($ordersData['payload']['Orders'] ?? []) . "\n";
    */
    
    echo "   ✓ Mock request would be rate-limited and retried if needed\n";
    echo "   ✓ All requests are logged automatically\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Orders request failed: {$e->getMessage()}\n\n";
}

// Example 3: Inventory updates with feed API
echo "3. Inventory updates using Feeds API (mock example):\n";

try {
    echo "   Preparing inventory feed...\n";
    
    // Mock inventory data
    $inventoryData = [
        [
            'sku' => 'TEST-SKU-001',
            'quantity' => 10,
            'price' => '19.99',
            'currency' => 'EUR'
        ],
        [
            'sku' => 'TEST-SKU-002',
            'quantity' => 5,
            'price' => '29.99',
            'currency' => 'EUR'
        ]
    ];
    
    echo "   Inventory items to update: " . count($inventoryData) . "\n";
    
    // Mock feed creation
    echo "   Creating feed document...\n";
    /*
    $feedResponse = $amazonClient->request('POST', '/feeds/2021-06-30/documents', [
        'json' => [
            'contentType' => 'application/json'
        ]
    ]);
    
    $feedDocument = json_decode($feedResponse->getContent(), true);
    $feedDocumentId = $feedDocument['feedDocumentId'];
    $uploadUrl = $feedDocument['url'];
    
    // Upload inventory data
    $uploadResponse = $amazonClient->request('PUT', $uploadUrl, [
        'body' => json_encode($inventoryData),
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ]);
    
    // Create the actual feed
    $feedCreateResponse = $amazonClient->request('POST', '/feeds/2021-06-30/feeds', [
        'json' => [
            'feedType' => 'POST_INVENTORY_AVAILABILITY_DATA',
            'marketplaceIds' => ['A1PA6795UKMFR9'],
            'inputFeedDocumentId' => $feedDocumentId
        ]
    ]);
    
    $feedData = json_decode($feedCreateResponse->getContent(), true);
    echo "   ✓ Feed created with ID: {$feedData['feedId']}\n";
    */
    
    echo "   ✓ Mock feed would be created and uploaded with rate limiting\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Feed creation failed: {$e->getMessage()}\n\n";
}

// Example 4: Multi-marketplace support
echo "4. Multi-marketplace configuration:\n";

$marketplaces = [
    'DE' => [
        'marketplace_id' => 'A1PA6795UKMFR9',
        'endpoint' => 'https://sellingpartnerapi-eu.amazon.com',
        'currency' => 'EUR'
    ],
    'UK' => [
        'marketplace_id' => 'A1F83G8C2ARO7P',
        'endpoint' => 'https://sellingpartnerapi-eu.amazon.com',
        'currency' => 'GBP'
    ],
    'FR' => [
        'marketplace_id' => 'A13V1IB3VIYZZH',
        'endpoint' => 'https://sellingpartnerapi-eu.amazon.com',
        'currency' => 'EUR'
    ]
];

foreach ($marketplaces as $country => $marketplaceConfig) {
    try {
        echo "   Creating client for {$country} marketplace:\n";
        
        $countryConfig = ClientConfig::create($marketplaceConfig['endpoint'])
            ->withAuthProvider($authProvider)
            ->withRateLimiterFactory($amazonRateLimiter)
            ->withHeaders([
                'x-amz-access-token' => 'your-access-token',
                'x-amzn-marketplace-id' => $marketplaceConfig['marketplace_id']
            ])
            ->withMiddleware(['logging', 'rate_limiting', 'retry'])
            ->build();
        
        $countryClient = $factory->createAmazonClient($countryConfig);
        
        echo "     ✓ {$country} client ready (Currency: {$marketplaceConfig['currency']})\n";
        
    } catch (Exception $e) {
        echo "     ✗ Failed to create {$country} client: {$e->getMessage()}\n";
    }
}

echo "\n";

// Example 5: Rate limit handling demonstration
echo "5. Rate limit handling:\n";

try {
    echo "   Demonstrating rate limit behavior...\n";
    
    // The rate limiter is configured to allow 20 requests per second
    echo "   Rate limit: 20 requests per second\n";
    echo "   Making rapid requests to test rate limiting...\n";
    
    $startTime = microtime(true);
    
    // Simulate multiple rapid requests
    for ($i = 1; $i <= 5; $i++) {
        $requestStart = microtime(true);
        
        // In a real scenario, this would make an actual request
        echo "   Request {$i}: ";
        
        try {
            // Mock request that would be rate limited
            usleep(50000); // Simulate 50ms API response time
            $requestTime = round((microtime(true) - $requestStart) * 1000, 2);
            echo "✓ completed in {$requestTime}ms\n";
            
        } catch (Exception $e) {
            echo "✗ failed: {$e->getMessage()}\n";
        }
    }
    
    $totalTime = round((microtime(true) - $startTime) * 1000, 2);
    echo "   Total time for 5 requests: {$totalTime}ms\n";
    echo "   ✓ Rate limiting would ensure compliance with API limits\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Rate limit demonstration failed: {$e->getMessage()}\n\n";
}

// Example 6: Error handling and retries
echo "6. Error handling and retry logic:\n";

try {
    echo "   Demonstrating retry behavior for transient errors...\n";
    
    // Mock scenarios that would trigger retries
    $retryScenarios = [
        ['status' => 429, 'error' => 'Rate limit exceeded'],
        ['status' => 500, 'error' => 'Internal server error'],
        ['status' => 503, 'error' => 'Service unavailable']
    ];
    
    foreach ($retryScenarios as $scenario) {
        echo "   Scenario: HTTP {$scenario['status']} ({$scenario['error']})\n";
        echo "     ✓ Would retry up to 3 times with exponential backoff\n";
        echo "     ✓ Backoff: 1s, 2s, 4s between attempts\n";
    }
    
    echo "   ✓ Retry logic ensures reliable API communication\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Error handling demonstration failed: {$e->getMessage()}\n\n";
}

echo str_repeat("=", 50) . "\n";
echo "Amazon SP-API integration examples completed!\n";
echo "The library provides comprehensive support for:\n";
echo "• Authentication with LWA tokens\n";
echo "• Intelligent rate limiting per API operation\n";
echo "• Automatic retries with exponential backoff\n";
echo "• Multi-marketplace support\n";
echo "• Comprehensive logging and monitoring\n";
echo "• Feed API support for bulk operations\n";