<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Transport;

/**
 * Factory for creating transport instances
 *
 * Provides a convenient way to create and discover available
 * HTTP transport implementations.
 */
class TransportFactory
{
    /** @var array<string, class-string<TransportInterface>> */
    private static array $transports = [
        'curl' => CurlTransport::class,
        'stream' => StreamTransport::class,
    ];

    /**
     * Create a transport instance by name
     *
     * @throws TransportException If transport is not available
     */
    public static function create(string $type): TransportInterface
    {
        if (!isset(self::$transports[$type])) {
            throw new TransportException("Unknown transport type: {$type}");
        }

        $transportClass = self::$transports[$type];
        $transport = new $transportClass();

        if (!$transport->isAvailable()) {
            throw TransportException::notAvailable($type);
        }

        return $transport;
    }

    /**
     * Create the best available transport
     *
     * Tries to create transports in order of preference:
     * 1. cURL (most features)
     * 2. Stream (fallback)
     */
    public static function createBest(): TransportInterface
    {
        $preferences = ['curl', 'stream'];

        foreach ($preferences as $type) {
            try {
                return self::create($type);
            } catch (TransportException $e) {
                // Try next transport
                continue;
            }
        }

        throw new TransportException('No HTTP transport available');
    }

    /**
     * Create transport optimized for a specific marketplace
     */
    public static function createForMarketplace(string $marketplace): TransportInterface
    {
        return match (strtolower($marketplace)) {
            'amazon' => self::create('curl'), // Amazon uploads need cURL
            'ebay' => self::create('curl'),   // eBay prefers cURL for complex requests
            'discogs' => self::createBest(),  // Discogs works with any transport
            'bandcamp' => self::create('stream'), // Bandcamp uses stream context
            default => self::createBest()
        };
    }

    /**
     * Get all available transport types
     *
     * @return array<string>
     */
    public static function getAvailable(): array
    {
        $available = [];

        foreach (array_keys(self::$transports) as $type) {
            try {
                $transport = new (self::$transports[$type])();
                if ($transport->isAvailable()) {
                    $available[] = $type;
                }
            } catch (\Exception $e) {
                // Transport not available
                continue;
            }
        }

        return $available;
    }

    /**
     * Get capabilities for all available transports
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getCapabilities(): array
    {
        $capabilities = [];

        foreach (self::getAvailable() as $type) {
            try {
                $transport = self::create($type);
                $capabilities[$type] = $transport->getCapabilities();
            } catch (TransportException $e) {
                // Skip unavailable transport
                continue;
            }
        }

        return $capabilities;
    }

    /**
     * Check if a specific transport is available
     */
    public static function isAvailable(string $type): bool
    {
        if (!isset(self::$transports[$type])) {
            return false;
        }

        try {
            $transport = new (self::$transports[$type])();
            return $transport->isAvailable();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Register a custom transport
     *
     * @param class-string<TransportInterface> $transportClass
     */
    public static function register(string $name, string $transportClass): void
    {
        if (!is_subclass_of($transportClass, TransportInterface::class)) {
            throw new \InvalidArgumentException(
                "Transport class must implement TransportInterface"
            );
        }

        self::$transports[$name] = $transportClass;
    }

    /**
     * Get transport recommendations for different use cases
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getRecommendations(): array
    {
        return [
            'production' => [
                'transport' => 'curl',
                'reason' => 'Most features, best performance, HTTP/2 support',
                'fallback' => 'stream'
            ],
            'development' => [
                'transport' => 'curl',
                'reason' => 'Better debugging capabilities and error reporting',
                'fallback' => 'stream'
            ],
            'minimal_dependencies' => [
                'transport' => 'stream',
                'reason' => 'No additional extensions required',
                'fallback' => null
            ],
            'file_uploads' => [
                'transport' => 'curl',
                'reason' => 'Better support for multipart/form-data uploads',
                'fallback' => 'stream'
            ],
            'large_downloads' => [
                'transport' => 'curl',
                'reason' => 'Progress callbacks and streaming support',
                'fallback' => 'stream'
            ],
            'ssl_client_certificates' => [
                'transport' => 'curl',
                'reason' => 'Full SSL/TLS client certificate support',
                'fallback' => null
            ]
        ];
    }

    /**
     * Get system information for transport diagnostics
     *
     * @return array<string, mixed>
     */
    public static function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'curl_available' => extension_loaded('curl'),
            'curl_version' => extension_loaded('curl') ? curl_version() : null,
            'openssl_available' => extension_loaded('openssl'),
            'allow_url_fopen' => ini_get('allow_url_fopen') == '1',
            'stream_wrappers' => stream_get_wrappers(),
            'available_transports' => self::getAvailable(),
            'transport_capabilities' => self::getCapabilities()
        ];
    }
}