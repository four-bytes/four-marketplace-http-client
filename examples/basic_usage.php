<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Four\MarketplaceHttp\Configuration\ClientConfig;
use Four\MarketplaceHttp\Factory\MarketplaceHttpClientFactory;
use Psr\Log\NullLogger;

/**
 * Basic usage example for the four-marketplace-http library
 *
 * This example demonstrates how to create HTTP clients for different marketplaces
 * with basic configuration and make API requests.
 */

// Create a logger (use your preferred logger)
$logger = new NullLogger();

// Create the factory
$factory = new MarketplaceHttpClientFactory($logger);

echo "Four Marketplace HTTP Client - Basic Usage Examples\n";
echo "==================================================\n\n";

// Example 1: Creating a basic client configuration
echo "1. Creating basic client configuration:\n";

$config = ClientConfig::create('https://api.example.com')
    ->withHeaders([
        'Accept' => 'application/json',
        'User-Agent' => 'MyApp/1.0'
    ])
    ->withTimeout(30.0)
    ->withMiddleware(['logging'])
    ->build();

echo "   Base URI: {$config->baseUri}\n";
echo "   Timeout: {$config->timeout} seconds\n";
echo "   Middleware: " . implode(', ', $config->middleware) . "\n\n";

// Example 2: Creating an Amazon SP-API client
echo "2. Creating Amazon SP-API client:\n";

try {
    $amazonConfig = ClientConfig::create('https://sellingpartnerapi-eu.amazon.com')
        ->withHeaders([
            'x-amz-access-token' => 'your-access-token-here'
        ])
        ->withMiddleware(['logging', 'rate_limiting', 'retry'])
        ->withTimeout(30.0)
        ->build();
    
    $amazonClient = $factory->createAmazonClient($amazonConfig);
    echo "   ✓ Amazon client created successfully\n";
    echo "   Client class: " . get_class($amazonClient) . "\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Failed to create Amazon client: {$e->getMessage()}\n\n";
}

// Example 3: Creating an eBay API client  
echo "3. Creating eBay API client:\n";

try {
    $ebayConfig = ClientConfig::create('https://api.ebay.com')
        ->withHeaders([
            'Authorization' => 'Bearer your-ebay-token-here'
        ])
        ->withMiddleware(['logging', 'rate_limiting'])
        ->withTimeout(25.0)
        ->build();
    
    $ebayClient = $factory->createEbayClient($ebayConfig);
    echo "   ✓ eBay client created successfully\n";
    echo "   Client class: " . get_class($ebayClient) . "\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Failed to create eBay client: {$e->getMessage()}\n\n";
}

// Example 4: Creating a Discogs API client
echo "4. Creating Discogs API client:\n";

try {
    $discogsConfig = ClientConfig::create('https://api.discogs.com')
        ->withHeaders([
            'Authorization' => 'Discogs token=your-discogs-token-here'
        ])
        ->withMiddleware(['logging', 'rate_limiting'])
        ->withTimeout(15.0)
        ->build();
    
    $discogsClient = $factory->createDiscogsClient($discogsConfig);
    echo "   ✓ Discogs client created successfully\n";
    echo "   Client class: " . get_class($discogsClient) . "\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Failed to create Discogs client: {$e->getMessage()}\n\n";
}

// Example 5: Creating a Bandcamp client
echo "5. Creating Bandcamp client:\n";

try {
    $bandcampConfig = ClientConfig::create('https://bandcamp.com/api')
        ->withHeaders([
            'Authorization' => 'Bearer your-bandcamp-token-here'
        ])
        ->withMiddleware(['logging', 'rate_limiting'])
        ->withTimeout(15.0)
        ->build();
    
    $bandcampClient = $factory->createBandcampClient($bandcampConfig);
    echo "   ✓ Bandcamp client created successfully\n";
    echo "   Client class: " . get_class($bandcampClient) . "\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Failed to create Bandcamp client: {$e->getMessage()}\n\n";
}

// Example 6: Using the fluent configuration builder
echo "6. Fluent configuration example:\n";

try {
    $fluentConfig = ClientConfig::create('https://api.marketplace.com')
        ->withAuth('bearer', 'your-token-here')
        ->withRateLimit('token_bucket', ['limit' => 10, 'rate' => ['1 second', 10]])
        ->withRetries(3, [500, 502, 503, 504])
        ->withTimeout(20.0)
        ->withUserAgent('FluentExample/1.0')
        ->withMiddleware(['logging', 'rate_limiting', 'retry'])
        ->build();
    
    $fluentClient = $factory->createClient($fluentConfig);
    echo "   ✓ Fluent client created successfully\n";
    echo "   Middleware count: " . count($fluentConfig->middleware) . "\n";
    echo "   Headers count: " . count($fluentConfig->defaultHeaders) . "\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Failed to create fluent client: {$e->getMessage()}\n\n";
}

// Example 7: Making actual requests (mock examples)
echo "7. Making API requests (mock examples):\n";

try {
    // Create a simple client for testing
    $testConfig = ClientConfig::create('https://httpbin.org')
        ->withMiddleware(['logging'])
        ->build();
    
    $testClient = $factory->createClient($testConfig);
    
    echo "   Making GET request to /get...\n";
    $response = $testClient->request('GET', '/get', [
        'query' => [
            'param1' => 'value1',
            'param2' => 'value2'
        ]
    ]);
    
    echo "   ✓ Response status: {$response->getStatusCode()}\n";
    echo "   ✓ Content type: " . ($response->getHeaders()['content-type'][0] ?? 'unknown') . "\n";
    
    // Get response content
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    if ($data && isset($data['args'])) {
        echo "   ✓ Query parameters received: " . implode(', ', array_keys($data['args'])) . "\n";
    }
    
} catch (Exception $e) {
    echo "   ✗ Request failed: {$e->getMessage()}\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Examples completed successfully!\n";
echo "Check the documentation for more advanced usage patterns.\n";