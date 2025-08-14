<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Client;

use Four\MarketplaceHttp\Factory\HttpClientFactoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Discogs API HTTP client with marketplace-specific optimizations
 *
 * Provides methods optimized for Discogs API interactions
 * with proper authentication, rate limiting, and error handling.
 */
class DiscogsClient extends MarketplaceClient
{
    public function getMarketplace(): string
    {
        return 'discogs';
    }

    protected function createHttpClient(HttpClientFactoryInterface $factory): HttpClientInterface
    {
        return $factory->createDiscogsClient($this->config);
    }

    /**
     * Search the Discogs database
     */
    public function search(array $params): ResponseInterface
    {
        return $this->get('/database/search', $params);
    }

    /**
     * Get release information by release ID
     */
    public function getRelease(int $releaseId): ResponseInterface
    {
        return $this->get("/releases/{$releaseId}");
    }

    /**
     * Get master release information
     */
    public function getMasterRelease(int $masterId): ResponseInterface
    {
        return $this->get("/masters/{$masterId}");
    }

    /**
     * Get master release versions
     */
    public function getMasterReleaseVersions(int $masterId, array $params = []): ResponseInterface
    {
        return $this->get("/masters/{$masterId}/versions", $params);
    }

    /**
     * Get artist information
     */
    public function getArtist(int $artistId): ResponseInterface
    {
        return $this->get("/artists/{$artistId}");
    }

    /**
     * Get artist releases
     */
    public function getArtistReleases(int $artistId, array $params = []): ResponseInterface
    {
        return $this->get("/artists/{$artistId}/releases", $params);
    }

    /**
     * Get label information
     */
    public function getLabel(int $labelId): ResponseInterface
    {
        return $this->get("/labels/{$labelId}");
    }

    /**
     * Get label releases
     */
    public function getLabelReleases(int $labelId, array $params = []): ResponseInterface
    {
        return $this->get("/labels/{$labelId}/releases", $params);
    }

    /**
     * Get user profile information
     */
    public function getUser(string $username): ResponseInterface
    {
        return $this->get("/users/{$username}");
    }

    /**
     * Get user collection
     */
    public function getUserCollection(string $username, array $params = []): ResponseInterface
    {
        return $this->get("/users/{$username}/collection/folders/0/releases", $params);
    }

    /**
     * Get user collection folders
     */
    public function getUserCollectionFolders(string $username): ResponseInterface
    {
        return $this->get("/users/{$username}/collection/folders");
    }

    /**
     * Get user wantlist
     */
    public function getUserWantlist(string $username, array $params = []): ResponseInterface
    {
        return $this->get("/users/{$username}/wants", $params);
    }

    /**
     * Get marketplace listings for a release
     */
    public function getMarketplaceListing(int $releaseId, array $params = []): ResponseInterface
    {
        return $this->get("/marketplace/listings", array_merge($params, ['release_id' => $releaseId]));
    }

    /**
     * Get specific marketplace listing
     */
    public function getMarketplaceListingById(int $listingId): ResponseInterface
    {
        return $this->get("/marketplace/listings/{$listingId}");
    }

    /**
     * Get user's marketplace inventory
     */
    public function getUserInventory(string $username, array $params = []): ResponseInterface
    {
        return $this->get("/users/{$username}/inventory", $params);
    }

    /**
     * Get user's marketplace orders (as seller)
     */
    public function getUserOrders(string $username, array $params = []): ResponseInterface
    {
        return $this->get("/marketplace/orders", array_merge($params, ['seller' => $username]));
    }

    /**
     * Get specific order details
     */
    public function getOrder(string $orderId): ResponseInterface
    {
        return $this->get("/marketplace/orders/{$orderId}");
    }

    /**
     * Update order status (requires authentication)
     */
    public function updateOrderStatus(string $orderId, string $status): ResponseInterface
    {
        return $this->post("/marketplace/orders/{$orderId}/messages", [
            'message' => "Order status updated to: {$status}",
            'status' => $status
        ]);
    }

    /**
     * Add item to user's collection (requires authentication)
     */
    public function addToCollection(int $folderId, int $releaseId, array $instanceData = []): ResponseInterface
    {
        $data = array_merge([
            'release_id' => $releaseId
        ], $instanceData);

        return $this->post("/users/{$this->getAuthenticatedUsername()}/collection/folders/{$folderId}/releases/{$releaseId}/instances", $data);
    }

    /**
     * Add item to user's wantlist (requires authentication)
     */
    public function addToWantlist(int $releaseId, string $notes = ''): ResponseInterface
    {
        return $this->put("/users/{$this->getAuthenticatedUsername()}/wants/{$releaseId}", [
            'notes' => $notes
        ]);
    }

    /**
     * Remove item from wantlist (requires authentication)
     */
    public function removeFromWantlist(int $releaseId): ResponseInterface
    {
        return $this->delete("/users/{$this->getAuthenticatedUsername()}/wants/{$releaseId}");
    }

    /**
     * Create marketplace listing (requires authentication)
     */
    public function createListing(array $listingData): ResponseInterface
    {
        return $this->post('/marketplace/listings', $listingData);
    }

    /**
     * Update marketplace listing (requires authentication)
     */
    public function updateListing(int $listingId, array $listingData): ResponseInterface
    {
        return $this->post("/marketplace/listings/{$listingId}", $listingData);
    }

    /**
     * Delete marketplace listing (requires authentication)
     */
    public function deleteListing(int $listingId): ResponseInterface
    {
        return $this->delete("/marketplace/listings/{$listingId}");
    }

    /**
     * Get marketplace statistics
     */
    public function getMarketplaceStats(int $releaseId): ResponseInterface
    {
        return $this->get("/marketplace/stats/{$releaseId}");
    }

    /**
     * Get price suggestions for a release
     */
    public function getPriceSuggestions(int $releaseId): ResponseInterface
    {
        return $this->get("/marketplace/price_suggestions/{$releaseId}");
    }

    /**
     * Get currency exchange rates
     */
    public function getCurrencyExchangeRates(): ResponseInterface
    {
        return $this->get('/marketplace/currencies');
    }

    /**
     * Get marketplace fee information
     */
    public function getMarketplaceFees(float $price, string $currency = 'USD'): ResponseInterface
    {
        return $this->get('/marketplace/fees', [
            'price' => $price,
            'currency' => $currency
        ]);
    }

    /**
     * Get authenticated user's identity (requires authentication)
     */
    public function getIdentity(): ResponseInterface
    {
        return $this->get('/oauth/identity');
    }

    /**
     * Helper method to get authenticated username
     */
    private function getAuthenticatedUsername(): string
    {
        // This would typically be cached after the first call
        try {
            $identity = $this->getIdentity();
            $data = $this->parseJsonResponse($identity);
            return $data['username'] ?? 'me';
        } catch (\Exception) {
            return 'me'; // Fallback to generic 'me' endpoint
        }
    }

    /**
     * Batch search for multiple releases
     */
    public function batchSearch(array $queries, int $perPage = 25): array
    {
        $results = [];
        
        foreach ($queries as $key => $query) {
            try {
                $response = $this->search(array_merge($query, ['per_page' => $perPage]));
                $results[$key] = $this->parseJsonResponse($response);
            } catch (\Exception $e) {
                $results[$key] = ['error' => $e->getMessage()];
            }
            
            // Small delay between requests to respect rate limits
            usleep(100000); // 100ms delay
        }
        
        return $results;
    }
}