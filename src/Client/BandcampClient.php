<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Client;

use Four\MarketplaceHttp\Factory\HttpClientFactoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Bandcamp HTTP client for unofficial API interactions
 *
 * Provides methods for interacting with Bandcamp's unofficial/undocumented API
 * with very conservative rate limiting and error handling.
 */
class BandcampClient extends MarketplaceClient
{
    public function getMarketplace(): string
    {
        return 'bandcamp';
    }

    protected function createHttpClient(HttpClientFactoryInterface $factory): HttpClientInterface
    {
        return $factory->createBandcampClient($this->config);
    }

    /**
     * Get fan data for a specific user
     */
    public function getFanData(string $fanId): ResponseInterface
    {
        return $this->get("/api/fan/2/fan_details", [
            'fan_id' => $fanId
        ]);
    }

    /**
     * Get collection for a fan
     */
    public function getFanCollection(string $fanId, array $params = []): ResponseInterface
    {
        $defaultParams = [
            'fan_id' => $fanId,
            'older_than_token' => $params['older_than_token'] ?? '',
            'count' => $params['count'] ?? 20
        ];

        return $this->get('/api/fancollection/1/collection_items', $defaultParams);
    }

    /**
     * Get band information
     */
    public function getBandInfo(string $bandId): ResponseInterface
    {
        return $this->get("/api/band/3/band_details", [
            'band_id' => $bandId
        ]);
    }

    /**
     * Get discography for a band
     */
    public function getBandDiscography(string $bandId): ResponseInterface
    {
        return $this->get("/api/band/3/discography", [
            'band_id' => $bandId
        ]);
    }

    /**
     * Get album information
     */
    public function getAlbumInfo(int $albumId): ResponseInterface
    {
        return $this->get("/api/tralbumdata/1/album", [
            'tralbum_id' => $albumId,
            'tralbum_type' => 'a' // 'a' for album, 't' for track
        ]);
    }

    /**
     * Get track information
     */
    public function getTrackInfo(int $trackId): ResponseInterface
    {
        return $this->get("/api/tralbumdata/1/album", [
            'tralbum_id' => $trackId,
            'tralbum_type' => 't' // 't' for track
        ]);
    }

    /**
     * Search for bands, albums, or tracks
     */
    public function search(string $query, array $params = []): ResponseInterface
    {
        $defaultParams = [
            'q' => $query,
            's' => $params['s'] ?? 'b', // 'b' for bands, 'a' for albums, 't' for tracks
            'p' => $params['p'] ?? 0, // page
            'g' => $params['g'] ?? 'all', // genre filter
            't' => $params['t'] ?? 'all', // tag filter
            'f' => $params['f'] ?? 'all', // format filter
        ];

        return $this->get('/search', $defaultParams);
    }

    /**
     * Get sales data for a band (requires authentication)
     */
    public function getSalesData(string $bandId, array $params = []): ResponseInterface
    {
        $defaultParams = [
            'band_id' => $bandId,
            'start_date' => $params['start_date'] ?? (new \DateTime('-30 days'))->format('Y-m-d'),
            'end_date' => $params['end_date'] ?? (new \DateTime())->format('Y-m-d')
        ];

        return $this->get('/api/sales/1/sales_data', $defaultParams);
    }

    /**
     * Get order details (requires authentication)
     */
    public function getOrderDetails(string $saleId): ResponseInterface
    {
        return $this->get("/api/sales/1/order_details", [
            'sale_id' => $saleId
        ]);
    }

    /**
     * Get fan funding data (requires authentication)
     */
    public function getFanFundingData(string $bandId, array $params = []): ResponseInterface
    {
        $defaultParams = [
            'band_id' => $bandId,
            'count' => $params['count'] ?? 50,
            'older_than_id' => $params['older_than_id'] ?? null
        ];

        return $this->get('/api/fanfunding/1/funding_data', array_filter($defaultParams));
    }

    /**
     * Update inventory/stock levels (requires authentication)
     */
    public function updateInventory(int $itemId, int $quantity): ResponseInterface
    {
        return $this->post('/api/merch/1/update_inventory', [
            'item_id' => $itemId,
            'quantity' => $quantity
        ]);
    }

    /**
     * Fulfill order (requires authentication)
     */
    public function fulfillOrder(string $saleId, array $fulfillmentData): ResponseInterface
    {
        return $this->post('/api/sales/1/fulfill_order', array_merge([
            'sale_id' => $saleId
        ], $fulfillmentData));
    }

    /**
     * Get discover feed
     */
    public function getDiscoverFeed(array $params = []): ResponseInterface
    {
        $defaultParams = [
            's' => $params['s'] ?? 'top', // sort: 'top', 'new', 'rec'
            'g' => $params['g'] ?? 'all', // genre
            't' => $params['t'] ?? 'all', // tag
            'f' => $params['f'] ?? 'all', // format
            'w' => $params['w'] ?? 0, // week offset
        ];

        return $this->get('/discover', $defaultParams);
    }

    /**
     * Get trending items
     */
    public function getTrending(array $params = []): ResponseInterface
    {
        $defaultParams = [
            'g' => $params['g'] ?? 'all', // genre
            'f' => $params['f'] ?? 'all', // format
        ];

        return $this->get('/api/discover/1/trending', $defaultParams);
    }

    /**
     * Get tag information
     */
    public function getTagInfo(string $tag): ResponseInterface
    {
        return $this->get("/api/hub/1/tag_details", [
            'tag' => $tag
        ]);
    }

    /**
     * Get items by tag
     */
    public function getItemsByTag(string $tag, array $params = []): ResponseInterface
    {
        $defaultParams = [
            'tag' => $tag,
            's' => $params['s'] ?? 'pop', // sort
            'p' => $params['p'] ?? 0, // page
            'f' => $params['f'] ?? 'all', // format
        ];

        return $this->get("/tag/{$tag}", $defaultParams);
    }

    /**
     * Get genre information
     */
    public function getGenreInfo(string $genre): ResponseInterface
    {
        return $this->get("/api/hub/1/genre_details", [
            'genre' => $genre
        ]);
    }

    /**
     * Get wishlists for an item
     */
    public function getWishlists(int $itemId, string $itemType = 'a'): ResponseInterface
    {
        return $this->get('/api/tralbumcollectors/1/wishlist_collectors', [
            'tralbum_id' => $itemId,
            'tralbum_type' => $itemType
        ]);
    }

    /**
     * Get followers for an item
     */
    public function getFollowers(int $itemId, string $itemType = 'a'): ResponseInterface
    {
        return $this->get('/api/tralbumcollectors/1/followers', [
            'tralbum_id' => $itemId,
            'tralbum_type' => $itemType
        ]);
    }

    /**
     * Helper method for batch operations with rate limiting
     */
    public function batchRequest(array $requests, float $delayBetweenRequests = 2.0): array
    {
        $results = [];
        
        foreach ($requests as $key => $request) {
            try {
                $method = $request['method'] ?? 'GET';
                $path = $request['path'];
                $params = $request['params'] ?? [];
                $headers = $request['headers'] ?? [];
                
                switch (strtoupper($method)) {
                    case 'GET':
                        $response = $this->get($path, $params, $headers);
                        break;
                    case 'POST':
                        $response = $this->post($path, $params, $headers);
                        break;
                    default:
                        throw new \InvalidArgumentException("Unsupported method: {$method}");
                }
                
                $results[$key] = [
                    'success' => true,
                    'data' => $this->parseJsonResponse($response),
                    'status_code' => $response->getStatusCode()
                ];
                
            } catch (\Exception $e) {
                $results[$key] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'status_code' => method_exists($e, 'getCode') ? $e->getCode() : 0
                ];
            }
            
            // Conservative delay between requests
            if ($key < count($requests) - 1) {
                usleep((int)($delayBetweenRequests * 1000000));
            }
        }
        
        return $results;
    }
}