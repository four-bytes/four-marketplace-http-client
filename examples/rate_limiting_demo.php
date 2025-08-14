<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Four\MarketplaceHttp\Configuration\ClientConfig;
use Four\MarketplaceHttp\Factory\MarketplaceHttpClientFactory;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Rate Limiting Demonstration
 *
 * This example demonstrates the advanced rate limiting capabilities
 * of the four-marketplace-http library, showing how different
 * marketplaces can have different rate limiting strategies.
 */

echo "Four Marketplace HTTP - Rate Limiting Demonstration\n";
echo "==================================================\n\n";

// Create logger and cache
$logger = new NullLogger();
$cache = new ArrayAdapter();

// Create factory
$factory = new MarketplaceHttpClientFactory($logger, $cache);

// Example 1: Amazon rate limiting (Token Bucket)
echo "1. Amazon SP-API Rate Limiting (Token Bucket Strategy):\n";

try {
    // Create Amazon rate limiter with token bucket strategy
    $amazonRateLimiter = $factory->createRateLimiterFactory('amazon', [
        'id' => 'amazon_orders',
        'policy' => 'token_bucket',
        'limit' => 10,  // 10 tokens in bucket
        'rate' => ['interval' => '1 second', 'amount' => 10] // Refill 10 tokens per second
    ]);
    
    $amazonConfig = ClientConfig::create('https://sellingpartnerapi-eu.amazon.com')
        ->withRateLimiterFactory($amazonRateLimiter)
        ->withMiddleware(['rate_limiting', 'logging'])
        ->build();
    
    $amazonClient = $factory->createAmazonClient($amazonConfig);
    
    echo "   Configuration:\n";
    echo "     • Strategy: Token Bucket\n";
    echo "     • Bucket size: 10 tokens\n";
    echo "     • Refill rate: 10 tokens/second\n";
    echo "     • Allows burst requests up to bucket size\n";
    
    echo "   \n   Simulating Amazon API requests:\n";
    
    $startTime = microtime(true);
    
    // Simulate burst of requests
    for ($i = 1; $i <= 12; $i++) {
        $requestStart = microtime(true);
        
        echo "     Request {$i}: ";
        
        try {
            // Simulate API call timing
            usleep(100000); // 100ms simulated response time
            
            $elapsed = round((microtime(true) - $requestStart) * 1000);
            echo "completed in {$elapsed}ms";
            
            // Show rate limiting effect
            if ($i > 10) {
                echo " (rate limited - waited for token refill)";
            }
            echo "\n";
            
        } catch (Exception $e) {
            echo "failed - {$e->getMessage()}\n";
        }
        
        // Small delay between requests to show refill
        if ($i === 10) {
            echo "     [Bucket exhausted - waiting for refill...]\n";
            usleep(500000); // 500ms pause
        }
    }
    
    $totalTime = round((microtime(true) - $startTime) * 1000);
    echo "   Total time: {$totalTime}ms\n\n";
    
} catch (Exception $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

// Example 2: eBay rate limiting (Fixed Window)
echo "2. eBay API Rate Limiting (Fixed Window Strategy):\n";

try {
    // Create eBay rate limiter with fixed window strategy
    $ebayRateLimiter = $factory->createRateLimiterFactory('ebay', [
        'id' => 'ebay_inventory',
        'policy' => 'fixed_window',
        'limit' => 5000,  // 5000 requests per day
        'rate' => ['interval' => '1 day']
    ]);
    
    $ebayConfig = ClientConfig::create('https://api.ebay.com')
        ->withRateLimiterFactory($ebayRateLimiter)
        ->withMiddleware(['rate_limiting', 'logging'])
        ->build();
    
    $ebayClient = $factory->createEbayClient($ebayConfig);
    
    echo "   Configuration:\n";
    echo "     • Strategy: Fixed Window\n";
    echo "     • Limit: 5000 requests/day\n";
    echo "     • Window resets at fixed intervals\n";
    echo "     • Suitable for daily quota limits\n";
    
    echo "   \n   Simulating eBay API requests:\n";
    
    for ($i = 1; $i <= 5; $i++) {
        echo "     Request {$i}: ";
        
        try {
            usleep(50000); // 50ms simulated response time
            echo "completed (quota remaining: " . (5000 - $i) . ")\n";
            
        } catch (Exception $e) {
            echo "failed - {$e->getMessage()}\n";
        }
    }
    
    echo "   ✓ Fixed window allows steady request rate within daily limit\n\n";
    
} catch (Exception $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

// Example 3: Discogs rate limiting (Sliding Window)
echo "3. Discogs API Rate Limiting (Sliding Window Strategy):\n";

try {
    // Create Discogs rate limiter with sliding window strategy
    $discogsRateLimiter = $factory->createRateLimiterFactory('discogs', [
        'id' => 'discogs_search',
        'policy' => 'sliding_window',
        'limit' => 60,    // 60 requests per minute
        'rate' => ['interval' => '1 minute']
    ]);
    
    $discogsConfig = ClientConfig::create('https://api.discogs.com')
        ->withRateLimiterFactory($discogsRateLimiter)
        ->withMiddleware(['rate_limiting', 'logging'])
        ->build();
    
    $discogsClient = $factory->createDiscogsClient($discogsConfig);
    
    echo "   Configuration:\n";
    echo "     • Strategy: Sliding Window\n";
    echo "     • Limit: 60 requests/minute\n";
    echo "     • Smooth rate limiting over time\n";
    echo "     • More precise than fixed window\n";
    
    echo "   \n   Simulating Discogs API requests:\n";
    
    for ($i = 1; $i <= 8; $i++) {
        echo "     Search request {$i}: ";
        
        try {
            usleep(200000); // 200ms simulated response time
            echo "completed (sliding window: " . (60 - $i) . " requests remaining in current minute)\n";
            
        } catch (Exception $e) {
            echo "failed - {$e->getMessage()}\n";
        }
        
        // Add slight delay to show sliding window behavior
        usleep(100000); // 100ms between requests
    }
    
    echo "   ✓ Sliding window provides smooth rate limiting\n\n";
    
} catch (Exception $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

// Example 4: Bandcamp rate limiting (Conservative)
echo "4. Bandcamp API Rate Limiting (Conservative Strategy):\n";

try {
    // Create Bandcamp rate limiter with very conservative limits
    $bandcampRateLimiter = $factory->createRateLimiterFactory('bandcamp', [
        'id' => 'bandcamp_orders',
        'policy' => 'token_bucket',
        'limit' => 2,     // Only 2 tokens in bucket
        'rate' => ['interval' => '1 second', 'amount' => 1] // Refill 1 token per second
    ]);
    
    $bandcampConfig = ClientConfig::create('https://bandcamp.com/api')
        ->withRateLimiterFactory($bandcampRateLimiter)
        ->withMiddleware(['rate_limiting', 'logging'])
        ->build();
    
    $bandcampClient = $factory->createBandcampClient($bandcampConfig);
    
    echo "   Configuration:\n";
    echo "     • Strategy: Conservative Token Bucket\n";
    echo "     • Bucket size: 2 tokens\n";
    echo "     • Refill rate: 1 token/second\n";
    echo "     • Suitable for unofficial APIs\n";
    
    echo "   \n   Simulating Bandcamp API requests:\n";
    
    for ($i = 1; $i <= 5; $i++) {
        $requestStart = microtime(true);
        echo "     Order request {$i}: ";
        
        try {
            usleep(300000); // 300ms simulated response time
            
            $elapsed = round((microtime(true) - $requestStart) * 1000);
            echo "completed in {$elapsed}ms";
            
            if ($i > 2) {
                echo " (rate limited - conservative approach)";
            }
            echo "\n";
            
        } catch (Exception $e) {
            echo "failed - {$e->getMessage()}\n";
        }
        
        // Pause to show token refill
        if ($i === 2) {
            echo "     [Conservative rate limiting - waiting...]\n";
            usleep(1000000); // 1 second pause
        }
    }
    
    echo "   ✓ Conservative rate limiting protects unofficial APIs\n\n";
    
} catch (Exception $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

// Example 5: Dynamic rate limit adjustment
echo "5. Dynamic Rate Limit Adjustment:\n";

try {
    echo "   The library can adjust rate limits based on API responses:\n\n";
    
    $responseCodes = [
        200 => "Success - maintain current rate",
        429 => "Rate limit exceeded - reduce rate temporarily", 
        500 => "Server error - back off and retry",
        503 => "Service unavailable - implement exponential backoff"
    ];
    
    foreach ($responseCodes as $code => $action) {
        echo "     HTTP {$code}: {$action}\n";
    }
    
    echo "\n   Rate limiter features:\n";
    echo "     ✓ Response header analysis (x-ratelimit-*, x-amzn-ratelimit-*)\n";
    echo "     ✓ Automatic backoff on 429 responses\n";
    echo "     ✓ Per-operation rate limiting (orders, inventory, feeds)\n";
    echo "     ✓ Marketplace-specific optimizations\n";
    echo "     ✓ Circuit breaker pattern for persistent failures\n\n";
    
} catch (Exception $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

// Example 6: Rate limit monitoring
echo "6. Rate Limit Monitoring and Analytics:\n";

try {
    echo "   Rate limiting provides comprehensive monitoring:\n\n";
    
    $metrics = [
        'requests_made' => 1247,
        'requests_blocked' => 23,
        'average_wait_time' => '145ms',
        'peak_usage' => '89%',
        'quota_remaining' => '73%',
        'window_resets_in' => '14min 32sec'
    ];
    
    foreach ($metrics as $metric => $value) {
        echo "     " . str_pad(ucfirst(str_replace('_', ' ', $metric)) . ':', 25) . $value . "\n";
    }
    
    echo "\n   Monitoring features:\n";
    echo "     ✓ Real-time rate limit status\n";
    echo "     ✓ Request success/failure rates\n";
    echo "     ✓ Average response times per marketplace\n";
    echo "     ✓ Rate limit violation alerts\n";
    echo "     ✓ Historical usage analytics\n";
    echo "     ✓ Performance optimization recommendations\n\n";
    
} catch (Exception $e) {
    echo "   Error: {$e->getMessage()}\n\n";
}

echo str_repeat("=", 50) . "\n";
echo "Rate limiting demonstration completed!\n\n";

echo "Summary of Rate Limiting Strategies:\n";
echo "• Token Bucket: Best for APIs that allow bursts (Amazon)\n";
echo "• Fixed Window: Simple daily/hourly quotas (eBay)\n";  
echo "• Sliding Window: Precise rate control (Discogs)\n";
echo "• Conservative: Protection for unofficial APIs (Bandcamp)\n\n";

echo "The library automatically:\n";
echo "• Selects optimal strategy per marketplace\n";
echo "• Adjusts rates based on API responses\n";
echo "• Provides detailed monitoring and analytics\n";
echo "• Prevents rate limit violations\n";
echo "• Ensures reliable API communication\n";