<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\Test\TestLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Base test case with common utilities for HTTP client testing
 */
abstract class TestCase extends BaseTestCase
{
    protected TestLogger $logger;
    protected ArrayAdapter $cache;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logger = new TestLogger();
        $this->cache = new ArrayAdapter();
    }
    
    /**
     * Create a mock HTTP client with predefined responses
     * 
     * @param array<MockResponse> $responses
     */
    protected function createMockClient(array $responses): HttpClientInterface
    {
        return new MockHttpClient($responses);
    }
    
    /**
     * Create a mock response with JSON content
     * 
     * @param array<string, mixed> $data
     * @param array<string, mixed> $headers
     */
    protected function createJsonResponse(
        array $data,
        int $status = 200,
        array $headers = []
    ): MockResponse {
        $defaultHeaders = [
            'Content-Type' => 'application/json',
        ];
        
        $headers = array_merge($defaultHeaders, $headers);
        
        return new MockResponse(json_encode($data), [
            'http_code' => $status,
            'response_headers' => $headers,
        ]);
    }
    
    /**
     * Create a mock response for rate limiting scenarios
     */
    protected function createRateLimitResponse(
        string $marketplace = 'general',
        int $limit = 100,
        int $remaining = 50
    ): MockResponse {
        $headers = match ($marketplace) {
            'amazon' => [
                'x-amzn-ratelimit-limit' => (string) $limit,
                'x-amzn-ratelimit-remaining' => (string) $remaining,
            ],
            'ebay' => [
                'x-ebay-api-analytics-daily-remaining' => (string) $remaining,
            ],
            'discogs' => [
                'x-discogs-ratelimit-remaining' => (string) $remaining,
            ],
            default => [
                'x-ratelimit-limit' => (string) $limit,
                'x-ratelimit-remaining' => (string) $remaining,
            ],
        };
        
        return new MockResponse('{"success": true}', [
            'http_code' => 200,
            'response_headers' => $headers,
        ]);
    }
    
    /**
     * Create a mock response for rate limit exceeded scenarios
     */
    protected function createRateExceededResponse(
        string $marketplace = 'general',
        int $retryAfter = 60
    ): MockResponse {
        $headers = [
            'Retry-After' => (string) $retryAfter,
        ];
        
        if ($marketplace === 'amazon') {
            $headers['x-amzn-ratelimit-limit'] = '0';
            $headers['x-amzn-ratelimit-remaining'] = '0';
        }
        
        return new MockResponse('{"error": "Rate limit exceeded"}', [
            'http_code' => 429,
            'response_headers' => $headers,
        ]);
    }
    
    /**
     * Create a mock response for server errors
     */
    protected function createServerErrorResponse(int $status = 500): MockResponse
    {
        return new MockResponse('{"error": "Internal server error"}', [
            'http_code' => $status,
            'response_headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }
    
    /**
     * Create mock Amazon SP-API responses
     */
    protected function createAmazonOrdersResponse(): MockResponse
    {
        $data = [
            'payload' => [
                'Orders' => [
                    [
                        'AmazonOrderId' => '123-TEST-ORDER-001',
                        'PurchaseDate' => '2025-08-13T10:00:00Z',
                        'LastUpdateDate' => '2025-08-13T10:30:00Z',
                        'OrderStatus' => 'Unshipped',
                        'FulfillmentChannel' => 'MFN',
                        'OrderTotal' => [
                            'Amount' => '39.98',
                            'CurrencyCode' => 'EUR'
                        ],
                        'MarketplaceId' => 'A1PA6795UKMFR9'
                    ]
                ]
            ]
        ];
        
        return $this->createJsonResponse($data, 200, [
            'x-amzn-ratelimit-limit' => '10',
            'x-amzn-ratelimit-remaining' => '8',
        ]);
    }
    
    /**
     * Create mock eBay inventory response
     */
    protected function createEbayInventoryResponse(): MockResponse
    {
        $data = [
            'inventoryItems' => [
                [
                    'sku' => 'TEST-SKU-001',
                    'availability' => [
                        'shipToLocationAvailability' => [
                            'quantity' => 10,
                        ]
                    ],
                    'condition' => 'NEW',
                ]
            ],
            'total' => 1,
            'size' => 1,
            'limit' => 25,
        ];
        
        return $this->createJsonResponse($data, 200, [
            'x-ebay-api-analytics-daily-remaining' => '4950',
        ]);
    }
    
    /**
     * Create mock Discogs search response
     */
    protected function createDiscogsSearchResponse(): MockResponse
    {
        $data = [
            'results' => [
                [
                    'id' => 12345,
                    'title' => 'Test Artist - Test Album',
                    'type' => 'release',
                    'year' => '2023',
                    'format' => ['CD'],
                    'label' => ['Test Label'],
                ]
            ],
            'pagination' => [
                'items' => 1,
                'page' => 1,
                'pages' => 1,
                'per_page' => 50,
            ]
        ];
        
        return $this->createJsonResponse($data, 200, [
            'x-discogs-ratelimit-remaining' => '58',
        ]);
    }
    
    /**
     * Create mock Bandcamp orders response
     */
    protected function createBandcampOrdersResponse(): MockResponse
    {
        $data = [
            'orders' => [
                [
                    'id' => 'bc-order-001',
                    'date' => '2025-08-13',
                    'total' => '19.99',
                    'currency' => 'EUR',
                    'items' => [
                        [
                            'sku' => 'BC-001',
                            'quantity' => 1,
                            'price' => '19.99'
                        ]
                    ]
                ]
            ],
            'total_count' => 1
        ];
        
        return $this->createJsonResponse($data);
    }
    
    /**
     * Assert that a specific log level was recorded
     */
    protected function assertLogLevel(string $level): void
    {
        $this->assertTrue($this->logger->hasRecords($level), "No {$level} log records found");
    }
    
    /**
     * Assert that a log message contains specific text
     */
    protected function assertLogMessage(string $level, string $message): void
    {
        $this->assertTrue(
            $this->logger->hasRecordThatContains($message, $level),
            "Log level {$level} does not contain message: {$message}"
        );
    }
    
    /**
     * Get all log records for inspection
     */
    protected function getLogRecords(): array
    {
        return $this->logger->records;
    }
    
    /**
     * Clear all log records
     */
    protected function clearLogs(): void
    {
        $this->logger->reset();
    }
    
    /**
     * Create a temporary test file
     */
    protected function createTempFile(string $content = ''): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'four_http_test_');
        if ($tempFile === false) {
            throw new \RuntimeException('Failed to create temporary file');
        }
        
        if (!empty($content)) {
            file_put_contents($tempFile, $content);
        }
        
        return $tempFile;
    }
    
    /**
     * Clean up temporary files after test
     */
    protected function tearDown(): void
    {
        // Clean up any temporary files if needed
        parent::tearDown();
    }
}