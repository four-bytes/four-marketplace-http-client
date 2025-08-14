<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Four\MarketplaceHttp\Transport\TransportFactory;
use Four\MarketplaceHttp\Transport\CurlTransport;
use Four\MarketplaceHttp\Transport\StreamTransport;
use Four\MarketplaceHttp\Transport\TransportException;

/**
 * Transport Layer Comparison and Demonstration
 *
 * This example demonstrates the different HTTP transport methods available
 * in the four-marketplace-http library and their capabilities.
 */

echo "Four Marketplace HTTP - Transport Layer Comparison\n";
echo "=================================================\n\n";

// Example 1: System Information and Available Transports
echo "1. System Information and Available Transports:\n";

$systemInfo = TransportFactory::getSystemInfo();

echo "   System Details:\n";
echo "     • PHP Version: {$systemInfo['php_version']}\n";
echo "     • cURL Available: " . ($systemInfo['curl_available'] ? 'Yes' : 'No') . "\n";
echo "     • OpenSSL Available: " . ($systemInfo['openssl_available'] ? 'Yes' : 'No') . "\n";
echo "     • allow_url_fopen: " . ($systemInfo['allow_url_fopen'] ? 'Enabled' : 'Disabled') . "\n";

if ($systemInfo['curl_available'] && $systemInfo['curl_version']) {
    echo "     • cURL Version: {$systemInfo['curl_version']['version']}\n";
    echo "     • SSL Version: {$systemInfo['curl_version']['ssl_version']}\n";
}

echo "\n   Available Transports: " . implode(', ', $systemInfo['available_transports']) . "\n\n";

// Example 2: Transport Capabilities Comparison
echo "2. Transport Capabilities Comparison:\n";

$capabilities = TransportFactory::getCapabilities();

foreach ($capabilities as $transport => $caps) {
    echo "   {$transport} Transport:\n";
    echo "     • Supported Methods: " . implode(', ', $caps['supported_methods']) . "\n";
    echo "     • SSL Support: " . ($caps['supports_ssl'] ? 'Yes' : 'No') . "\n";
    echo "     • Redirects: " . ($caps['supports_redirects'] ? 'Yes' : 'No') . "\n";
    echo "     • Cookies: " . ($caps['supports_cookies'] ? 'Yes' : 'No') . "\n";
    echo "     • Streaming: " . ($caps['supports_streaming'] ? 'Yes' : 'No') . "\n";
    echo "     • Async: " . ($caps['supports_async'] ? 'Yes' : 'No') . "\n";
    
    if (isset($caps['supports_http2'])) {
        echo "     • HTTP/2: " . ($caps['supports_http2'] ? 'Yes' : 'No') . "\n";
    }
    
    echo "\n";
}

// Example 3: Performance Testing
echo "3. Performance Testing (Mock Requests):\n";

$testUrl = 'https://httpbin.org/json';
$testMethods = ['GET', 'POST'];

foreach ($systemInfo['available_transports'] as $transportType) {
    try {
        echo "   Testing {$transportType} transport:\n";
        
        $transport = TransportFactory::create($transportType);
        $totalTime = 0;
        $successCount = 0;
        
        foreach ($testMethods as $method) {
            $startTime = microtime(true);
            
            try {
                $options = [
                    'headers' => [
                        'User-Agent' => 'Four-MarketplaceHttp-Test/1.0',
                        'Accept' => 'application/json'
                    ],
                    'timeout' => 10.0
                ];
                
                if ($method === 'POST') {
                    $options['body'] = json_encode(['test' => 'data']);
                    $options['headers']['Content-Type'] = 'application/json';
                }
                
                $response = $transport->request($method, $testUrl, $options);
                $endTime = microtime(true);
                $requestTime = ($endTime - $startTime) * 1000;
                
                echo "     {$method}: {$response->getStatusCode()} ({$requestTime:.2f}ms)\n";
                $totalTime += $requestTime;
                $successCount++;
                
            } catch (TransportException $e) {
                echo "     {$method}: Failed - {$e->getMessage()}\n";
            }
        }
        
        if ($successCount > 0) {
            $avgTime = $totalTime / $successCount;
            echo "     Average: {$avgTime:.2f}ms\n";
        }
        
        echo "\n";
        
    } catch (TransportException $e) {
        echo "     Transport not available: {$e->getMessage()}\n\n";
    }
}

// Example 4: Marketplace-Specific Transport Selection
echo "4. Marketplace-Specific Transport Selection:\n";

$marketplaces = ['amazon', 'ebay', 'discogs', 'bandcamp'];

foreach ($marketplaces as $marketplace) {
    try {
        $transport = TransportFactory::createForMarketplace($marketplace);
        echo "   {$marketplace}: {$transport->getType()} transport (optimized)\n";
    } catch (TransportException $e) {
        echo "   {$marketplace}: No suitable transport available\n";
    }
}

echo "\n";

// Example 5: Transport Recommendations
echo "5. Transport Recommendations by Use Case:\n";

$recommendations = TransportFactory::getRecommendations();

foreach ($recommendations as $useCase => $rec) {
    echo "   " . ucwords(str_replace('_', ' ', $useCase)) . ":\n";
    echo "     • Recommended: {$rec['transport']}\n";
    echo "     • Reason: {$rec['reason']}\n";
    
    if ($rec['fallback']) {
        echo "     • Fallback: {$rec['fallback']}\n";
    }
    
    echo "\n";
}

// Example 6: Advanced Transport Configuration
echo "6. Advanced Transport Configuration:\n";

try {
    echo "   cURL Transport Advanced Features:\n";
    
    $curlTransport = new CurlTransport();
    
    if ($curlTransport->isAvailable()) {
        // Test with advanced options
        $response = $curlTransport->request('GET', 'https://httpbin.org/headers', [
            'headers' => [
                'User-Agent' => 'Advanced-Test/1.0',
                'X-Custom-Header' => 'test-value'
            ],
            'timeout' => 15.0,
            'follow_redirects' => true,
            'max_redirects' => 5,
            'verify_ssl' => true
        ]);
        
        echo "     ✓ Advanced configuration successful\n";
        echo "     ✓ Status: {$response->getStatusCode()}\n";
        echo "     ✓ Content-Type: {$response->getContentType()}\n";
        
        if ($response->getRequestDuration()) {
            echo "     ✓ Request Duration: {$response->getRequestDuration():.3f}s\n";
        }
    }
    
    echo "\n   Stream Transport Configuration:\n";
    
    $streamTransport = new StreamTransport();
    
    if ($streamTransport->isAvailable()) {
        $response = $streamTransport->request('GET', 'https://httpbin.org/user-agent', [
            'headers' => [
                'User-Agent' => 'Stream-Test/1.0'
            ],
            'timeout' => 10.0,
            'follow_redirects' => true
        ]);
        
        echo "     ✓ Stream transport successful\n";  
        echo "     ✓ Status: {$response->getStatusCode()}\n";
        echo "     ✓ Transport Type: {$response->getInfoValue('transport_type')}\n";
    }
    
} catch (TransportException $e) {
    echo "   Error testing advanced features: {$e->getMessage()}\n";
}

echo "\n";

// Example 7: Error Handling Demonstration
echo "7. Error Handling Demonstration:\n";

$errorScenarios = [
    ['url' => 'https://httpbin.org/status/404', 'desc' => '404 Not Found'],
    ['url' => 'https://httpbin.org/status/500', 'desc' => '500 Server Error'],
    ['url' => 'https://httpbin.org/delay/30', 'desc' => 'Timeout Test', 'timeout' => 2.0],
    ['url' => 'https://invalid-domain-that-should-not-exist.com', 'desc' => 'Connection Failed']
];

foreach ($errorScenarios as $scenario) {
    echo "   Testing: {$scenario['desc']}\n";
    
    try {
        $transport = TransportFactory::createBest();
        
        $options = [
            'timeout' => $scenario['timeout'] ?? 10.0
        ];
        
        $response = $transport->request('GET', $scenario['url'], $options);
        
        echo "     Status: {$response->getStatusCode()}\n";
        
        if ($response->isSuccessful()) {
            echo "     ✓ Request successful\n";
        } elseif ($response->isClientError()) {
            echo "     ! Client error (4xx)\n";
        } elseif ($response->isServerError()) {
            echo "     ! Server error (5xx)\n";
        }
        
    } catch (TransportException $e) {
        echo "     ✗ Transport error: {$e->getMessage()}\n";
        echo "     ✗ Error code: {$e->getCode()}\n";
        
        if ($e->getTransportType()) {
            echo "     ✗ Transport: {$e->getTransportType()}\n";
        }
    }
    
    echo "\n";
}

// Example 8: Best Practices Summary
echo "8. Best Practices Summary:\n";
echo "   ✓ Use cURL transport for production environments\n";
echo "   ✓ Use stream transport for minimal dependency setups\n"; 
echo "   ✓ Always handle TransportException in your code\n";
echo "   ✓ Set appropriate timeouts for your use case\n";
echo "   ✓ Use TransportFactory::createForMarketplace() for optimal performance\n";
echo "   ✓ Test both transport methods in your application\n";
echo "   ✓ Monitor transport performance and success rates\n";
echo "   ✓ Use SSL verification in production (verify_ssl: true)\n";
echo "   ✓ Configure appropriate User-Agent headers\n";
echo "   ✓ Handle HTTP status codes appropriately\n\n";

echo str_repeat("=", 50) . "\n";
echo "Transport comparison completed!\n\n";

echo "Key Takeaways:\n";
echo "• cURL provides the most features and best performance\n";
echo "• Stream transport is a reliable fallback option\n";
echo "• Transport selection can be automated based on availability\n";
echo "• Each marketplace may prefer different transport methods\n";
echo "• Comprehensive error handling is essential\n";
echo "• Performance monitoring helps optimize API integrations\n";