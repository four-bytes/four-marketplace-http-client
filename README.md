# Four Marketplace HTTP Client Library

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue)](https://www.php.net/releases/8.4/en.php)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/Tests-PHPUnit-orange)](phpunit.xml)
[![Quality](https://img.shields.io/badge/PHPStan-Level%208-brightgreen)](phpstan.neon)

A modern PHP 8.4+ HTTP client factory and middleware library specifically designed for marketplace API integrations. Provides unified abstractions for different HTTP transport methods, comprehensive rate limiting, authentication, and error handling.

## Features

### 🚀 **Modern PHP 8.4+ Architecture**
- Strict typing with `declare(strict_types=1)`
- Property hooks and constructor property declarations
- PSR-4 autoloading and PSR-18 HTTP client interface compliance
- PHPStan level 8 compliance

### 🏪 **Marketplace-Specific Optimizations**
- **Amazon SP-API**: Dynamic rate limiting, LWA authentication, feed uploads
- **eBay API**: Trading API support, OAuth 2.0, inventory management
- **Discogs API**: OAuth 1.0a authentication, conservative rate limiting
- **Bandcamp API**: Multi-seller support, stream context transport

### 🔄 **Multiple Transport Methods**
- **cURL Transport**: Full-featured, HTTP/2 support, file uploads
- **Stream Transport**: No dependencies, `stream_context_create` based
- **Auto-detection**: Automatically selects best available transport

### ⚡ **Advanced Rate Limiting**
- **Token Bucket**: Burst support (Amazon, Bandcamp)
- **Fixed Window**: Daily quotas (eBay)
- **Sliding Window**: Precise control (Discogs)
- **Response Analysis**: Auto-adjusts based on API headers

### 🔐 **Authentication Support**
- Bearer tokens, API keys, Basic auth
- OAuth 2.0 with automatic token refresh
- OAuth 1.0a with signature generation (Discogs)
- Custom authentication providers

### 🛡️ **Enterprise Features**
- Comprehensive middleware system
- Automatic retries with exponential backoff
- Request/response logging with PSR-3
- Circuit breaker patterns
- Performance monitoring

## Installation

```bash
composer require four-bytes/four-marketplace-http
```

## Requirements

- **PHP 8.4+** with strict typing support
- **Symfony HTTP Client 7.2+** for base functionality
- **Symfony Rate Limiter 7.2+** for rate limiting
- **PSR-3 Logger** for logging support (optional)
- **PSR-6 Cache** for caching support (optional)

### Optional Extensions
- **cURL** for advanced HTTP features (recommended)
- **OpenSSL** for SSL/TLS support

## Quick Start

### Basic Usage

```php
<?php

use Four\MarketplaceHttp\Configuration\ClientConfig;
use Four\MarketplaceHttp\Factory\MarketplaceHttpClientFactory;

// Create factory
$factory = new MarketplaceHttpClientFactory();

// Create client with fluent configuration
$client = $factory->createClient(
    ClientConfig::create('https://api.example.com')
        ->withAuth('bearer', 'your-token')
        ->withTimeout(30.0)
        ->withMiddleware(['logging', 'rate_limiting'])
        ->build()
);

// Make requests
$response = $client->request('GET', '/api/data');
$data = json_decode($response->getContent(), true);
```

### Amazon SP-API Integration

```php
<?php

use Four\MarketplaceHttp\Configuration\ClientConfig;
use Four\MarketplaceHttp\Factory\MarketplaceHttpClientFactory;

$factory = new MarketplaceHttpClientFactory($logger, $cache);

// Create Amazon-optimized client
$amazonClient = $factory->createAmazonClient(
    ClientConfig::create('https://sellingpartnerapi-eu.amazon.com')
        ->forAmazon() // Apply Amazon-specific defaults
        ->withAuth('bearer', $lwaAccessToken)
        ->withHeaders([
            'x-amzn-marketplace-id' => 'A1PA6795UKMFR9'
        ])
        ->build()
);

// Fetch orders with automatic rate limiting
$response = $amazonClient->request('GET', '/orders/v0/orders', [
    'query' => [
        'MarketplaceIds' => 'A1PA6795UKMFR9',
        'CreatedAfter' => '2025-01-01T00:00:00Z'
    ]
]);

$orders = json_decode($response->getContent(), true);
```

### Discogs OAuth 1.0a Integration

```php
<?php

use Four\MarketplaceHttp\Authentication\OAuth1aProvider;
use Four\MarketplaceHttp\Configuration\ClientConfig;
use Four\MarketplaceHttp\Factory\MarketplaceHttpClientFactory;

// Create OAuth 1.0a provider
$authProvider = OAuth1aProvider::discogs(
    'your-consumer-key',
    'your-consumer-secret', 
    'your-access-token',
    'your-token-secret'
);

$factory = new MarketplaceHttpClientFactory();

// Create Discogs client with OAuth 1.0a
$discogsClient = $factory->createDiscogsClient(
    ClientConfig::create('https://api.discogs.com')
        ->forDiscogs()
        ->withAuthentication($authProvider)
        ->build()
);

// Search releases
$response = $discogsClient->request('GET', '/database/search', [
    'query' => [
        'type' => 'release',
        'artist' => 'Pink Floyd',
        'title' => 'Dark Side of the Moon'
    ]
]);
```

## Configuration

### Fluent Configuration Builder

```php
<?php

use Four\MarketplaceHttp\Configuration\ClientConfig;

$config = ClientConfig::create('https://api.marketplace.com')
    // Authentication
    ->withAuth('bearer', 'your-token')
    
    // Headers
    ->withUserAgent('MyApp/1.0')
    ->withAccept('application/json')
    ->withContentType('application/json')
    
    // Timeouts and behavior
    ->withTimeout(45.0)
    ->withMaxRedirects(5)
    
    // Middleware
    ->withMiddleware(['logging', 'rate_limiting', 'retry'])
    
    // Marketplace presets
    ->forProduction() // or ->forDevelopment()
    
    ->build();
```

### Marketplace-Specific Configurations

```php
// Amazon SP-API optimized
$amazonConfig = ClientConfig::create($amazonEndpoint)->forAmazon();

// eBay API optimized  
$ebayConfig = ClientConfig::create($ebayEndpoint)->forEbay();

// Discogs API optimized
$discogsConfig = ClientConfig::create($discogsEndpoint)->forDiscogs();

// Bandcamp API optimized
$bandcampConfig = ClientConfig::create($bandcampEndpoint)->forBandcamp();
```

## Rate Limiting

### Built-in Strategies

The library provides different rate limiting strategies optimized for each marketplace:

```php
<?php

use Four\MarketplaceHttp\Factory\MarketplaceHttpClientFactory;

$factory = new MarketplaceHttpClientFactory();

// Amazon: Token Bucket (20 req/sec with bursts)
$amazonLimiter = $factory->createRateLimiterFactory('amazon', [
    'limit' => 20,
    'rate' => ['interval' => '1 second', 'amount' => 20]
]);

// eBay: Fixed Window (5000 req/day)
$ebayLimiter = $factory->createRateLimiterFactory('ebay', [
    'limit' => 5000,
    'rate' => ['interval' => '1 day']
]);

// Discogs: Sliding Window (60 req/minute)
$discogsLimiter = $factory->createRateLimiterFactory('discogs', [
    'limit' => 60,
    'rate' => ['interval' => '1 minute']
]);
```

### Rate Limit Monitoring

Rate limits are automatically monitored and logged:

```php
// Rate limit information is extracted from response headers:
// - Amazon: x-amzn-ratelimit-limit, x-amzn-ratelimit-remaining  
// - eBay: x-ebay-api-analytics-daily-remaining
// - Discogs: x-discogs-ratelimit-remaining
// - Generic: x-ratelimit-limit, x-ratelimit-remaining
```

## Transport Layer

### Automatic Transport Selection

```php
<?php

use Four\MarketplaceHttp\Transport\TransportFactory;

// Get the best available transport
$transport = TransportFactory::createBest();

// Get marketplace-optimized transport
$amazonTransport = TransportFactory::createForMarketplace('amazon');

// Check available transports
$available = TransportFactory::getAvailable(); // ['curl', 'stream']

// Get system information
$sysInfo = TransportFactory::getSystemInfo();
```

### Manual Transport Configuration

```php
<?php

use Four\MarketplaceHttp\Transport\CurlTransport;
use Four\MarketplaceHttp\Transport\StreamTransport;

// cURL transport (recommended)
$curlTransport = new CurlTransport();
$response = $curlTransport->request('GET', 'https://api.example.com/data');

// Stream transport (fallback)
$streamTransport = new StreamTransport();  
$response = $streamTransport->request('GET', 'https://api.example.com/data');
```

## Authentication

### OAuth 2.0 (Amazon, eBay)

```php
<?php

use Four\MarketplaceHttp\Authentication\OAuthProvider;

// Amazon LWA OAuth
$amazonAuth = OAuthProvider::amazon(
    'your-client-id',
    'your-client-secret', 
    'your-refresh-token',
    $httpClient,
    $requestFactory,
    $streamFactory
);

// eBay OAuth
$ebayAuth = OAuthProvider::ebay(
    'your-client-id',
    'your-client-secret',
    'your-refresh-token', 
    $httpClient,
    $requestFactory,
    $streamFactory,
    ['https://api.ebay.com/oauth/api_scope']
);
```

### OAuth 1.0a (Discogs)

```php
<?php

use Four\MarketplaceHttp\Authentication\OAuth1aProvider;

$discogsAuth = OAuth1aProvider::discogs(
    'your-consumer-key',
    'your-consumer-secret',
    'your-access-token', 
    'your-token-secret'
);

// Test signature generation
$testResult = $discogsAuth->testSignature();
```

### Simple Authentication

```php
<?php

// Bearer token
$config->withAuth('bearer', 'your-access-token');

// API key
$config->withAuth('api_key', 'your-api-key');  

// Basic auth
$config->withAuth('basic', 'username:password');

// Custom token
$config->withAuth('token', 'your-token');
```

## Middleware System

### Available Middleware

- **Logging**: PSR-3 compatible request/response logging
- **Rate Limiting**: Automatic rate limit enforcement  
- **Retry**: Exponential backoff retry logic
- **Authentication**: OAuth and token management
- **Performance**: Request timing and monitoring
- **Caching**: Response caching (planned)

### Custom Middleware

```php
<?php

use Four\MarketplaceHttp\Middleware\MiddlewareInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CustomMiddleware implements MiddlewareInterface
{
    public function wrap(HttpClientInterface $client): HttpClientInterface
    {
        return new CustomHttpClientWrapper($client);
    }
    
    public function getName(): string
    {
        return 'custom';
    }
    
    public function getPriority(): int
    {
        return 100;
    }
}
```

## Error Handling

### Exception Hierarchy

```php
<?php

use Four\MarketplaceHttp\Exception\HttpClientException;
use Four\MarketplaceHttp\Exception\RateLimitException;
use Four\MarketplaceHttp\Exception\AuthenticationException;
use Four\MarketplaceHttp\Exception\RetryableException;

try {
    $response = $client->request('GET', '/api/data');
} catch (RateLimitException $e) {
    // Handle rate limiting (429 responses)
    $waitTime = $e->getRetryAfter();
    sleep($waitTime);
} catch (AuthenticationException $e) {
    // Handle auth errors (401, 403)
    $this->refreshToken();
} catch (RetryableException $e) {
    // Handle temporary errors (500, 502, 503)
    $this->retryLater($e);
} catch (HttpClientException $e) {
    // Handle other HTTP errors
    $this->logError($e);
}
```

## Performance Monitoring

### Built-in Monitoring

```php
<?php

use Four\MarketplaceHttp\Configuration\ClientConfig;

$config = ClientConfig::create('https://api.example.com')
    ->withPerformanceMonitoring()
    ->build();

$client = $factory->createClient($config);

// Performance data is automatically collected:
// - Request/response times
// - Success/failure rates  
// - Rate limit utilization
// - Error frequencies
```

## Testing

### Mock Responses

```php
<?php

use Four\MarketplaceHttp\Tests\TestCase;

class MyApiTest extends TestCase
{
    public function testApiCall(): void
    {
        $mockClient = $this->createMockClient([
            $this->createJsonResponse(['status' => 'success']),
            $this->createRateLimitResponse('amazon', 10, 8)
        ]);
        
        // Test your API integration
        $response = $mockClient->request('GET', '/test');
        $this->assertSame(200, $response->getStatusCode());
    }
}
```

### Integration Testing

```php
<?php

// Run tests
composer test

// Coverage report
composer test-coverage

// Quality analysis  
composer phpstan

// Combined quality check
composer quality
```

## Examples

The `examples/` directory contains comprehensive usage examples:

- `basic_usage.php` - Getting started guide
- `amazon_integration.php` - Complete Amazon SP-API integration
- `rate_limiting_demo.php` - Rate limiting strategies demonstration
- `oauth_examples.php` - OAuth 1.0a and 2.0 authentication  
- `transport_comparison.php` - Transport layer comparison

Run examples:

```bash
php examples/basic_usage.php
php examples/amazon_integration.php
php examples/rate_limiting_demo.php
```

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make changes with tests: `composer test` 
4. Ensure quality: `composer quality`
5. Submit pull request

### Development Standards

- **PHP 8.4+** with strict typing
- **PHPStan level 8** compliance
- **100% test coverage** for new features
- **PSR-12** coding standards
- **Comprehensive documentation**

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- **Documentation**: Full API documentation available
- **Issues**: Report issues on GitHub
- **Examples**: Comprehensive examples provided
- **Community**: PHP community support

## Changelog

### v1.0.0 (2025-08-13)

**Initial Release**

- ✅ Complete marketplace HTTP client factory
- ✅ Multiple transport methods (cURL, Stream)
- ✅ Advanced rate limiting strategies
- ✅ OAuth 1.0a and 2.0 authentication
- ✅ Comprehensive middleware system
- ✅ Amazon, eBay, Discogs, Bandcamp optimizations
- ✅ PHPUnit test suite with mocks
- ✅ PHPStan level 8 compliance
- ✅ Production-ready architecture

---

**Four Bytes** | **Modern PHP Libraries for Marketplace Integration**

Built with ❤️ for the PHP community