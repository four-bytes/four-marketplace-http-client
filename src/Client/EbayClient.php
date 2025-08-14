<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Client;

use Four\MarketplaceHttp\Factory\HttpClientFactoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * eBay API HTTP client with marketplace-specific optimizations
 *
 * Provides methods optimized for eBay REST API interactions
 * with proper authentication, rate limiting, and error handling.
 */
class EbayClient extends MarketplaceClient
{
    public function getMarketplace(): string
    {
        return 'ebay';
    }

    protected function createHttpClient(HttpClientFactoryInterface $factory): HttpClientInterface
    {
        return $factory->createEbayClient($this->config);
    }

    /**
     * Get orders from the Fulfillment API
     */
    public function getOrders(array $filters = []): ResponseInterface
    {
        $params = [];

        if (isset($filters['creation_date_range_from'])) {
            $params['filter'] = "creationdate:[{$filters['creation_date_range_from']}..{$filters['creation_date_range_to']}]";
        }

        if (isset($filters['order_fulfillment_status'])) {
            $fulfillmentFilter = "orderfulfillmentstatus:{" . implode('|', $filters['order_fulfillment_status']) . "}";
            $params['filter'] = isset($params['filter']) 
                ? $params['filter'] . ',' . $fulfillmentFilter 
                : $fulfillmentFilter;
        }

        if (isset($filters['limit'])) {
            $params['limit'] = $filters['limit'];
        }

        if (isset($filters['offset'])) {
            $params['offset'] = $filters['offset'];
        }

        return $this->get('/sell/fulfillment/v1/order', $params);
    }

    /**
     * Get a specific order by eBay Order ID
     */
    public function getOrder(string $orderId): ResponseInterface
    {
        return $this->get("/sell/fulfillment/v1/order/{$orderId}");
    }

    /**
     * Update order fulfillment status
     */
    public function updateOrderFulfillment(string $orderId, array $fulfillmentData): ResponseInterface
    {
        return $this->post("/sell/fulfillment/v1/order/{$orderId}/shipping_fulfillment", $fulfillmentData);
    }

    /**
     * Get inventory items
     */
    public function getInventoryItems(array $params = []): ResponseInterface
    {
        return $this->get('/sell/inventory/v1/inventory_item', $params);
    }

    /**
     * Get a specific inventory item by SKU
     */
    public function getInventoryItem(string $sku): ResponseInterface
    {
        return $this->get("/sell/inventory/v1/inventory_item/{$sku}");
    }

    /**
     * Create or update inventory item
     */
    public function upsertInventoryItem(string $sku, array $inventoryData): ResponseInterface
    {
        return $this->put("/sell/inventory/v1/inventory_item/{$sku}", $inventoryData);
    }

    /**
     * Delete inventory item
     */
    public function deleteInventoryItem(string $sku): ResponseInterface
    {
        return $this->delete("/sell/inventory/v1/inventory_item/{$sku}");
    }

    /**
     * Get offers for an inventory item
     */
    public function getOffers(string $sku): ResponseInterface
    {
        return $this->get("/sell/inventory/v1/inventory_item/{$sku}/offer");
    }

    /**
     * Create an offer for an inventory item
     */
    public function createOffer(string $sku, array $offerData): ResponseInterface
    {
        return $this->post("/sell/inventory/v1/inventory_item/{$sku}/offer", $offerData);
    }

    /**
     * Update an offer
     */
    public function updateOffer(string $offerId, array $offerData): ResponseInterface
    {
        return $this->put("/sell/inventory/v1/offer/{$offerId}", $offerData);
    }

    /**
     * Publish an offer
     */
    public function publishOffer(string $offerId): ResponseInterface
    {
        return $this->post("/sell/inventory/v1/offer/{$offerId}/publish");
    }

    /**
     * Withdraw an offer
     */
    public function withdrawOffer(string $offerId): ResponseInterface
    {
        return $this->post("/sell/inventory/v1/offer/{$offerId}/withdraw");
    }

    /**
     * Get account information
     */
    public function getAccount(): ResponseInterface
    {
        return $this->get('/sell/account/v1/account');
    }

    /**
     * Get user preferences
     */
    public function getUserPreferences(): ResponseInterface
    {
        return $this->get('/sell/account/v1/user_preference');
    }

    /**
     * Get shipping policies
     */
    public function getShippingPolicies(array $params = []): ResponseInterface
    {
        return $this->get('/sell/account/v1/shipping_policy', $params);
    }

    /**
     * Get payment policies
     */
    public function getPaymentPolicies(array $params = []): ResponseInterface
    {
        return $this->get('/sell/account/v1/payment_policy', $params);
    }

    /**
     * Get return policies
     */
    public function getReturnPolicies(array $params = []): ResponseInterface
    {
        return $this->get('/sell/account/v1/return_policy', $params);
    }

    /**
     * Create shipping policy
     */
    public function createShippingPolicy(array $policyData): ResponseInterface
    {
        return $this->post('/sell/account/v1/shipping_policy', $policyData);
    }

    /**
     * Create payment policy
     */
    public function createPaymentPolicy(array $policyData): ResponseInterface
    {
        return $this->post('/sell/account/v1/payment_policy', $policyData);
    }

    /**
     * Create return policy
     */
    public function createReturnPolicy(array $policyData): ResponseInterface
    {
        return $this->post('/sell/account/v1/return_policy', $policyData);
    }

    /**
     * Get analytics data
     */
    public function getTrafficReport(array $params): ResponseInterface
    {
        return $this->get('/sell/analytics/v1/traffic_report', $params);
    }

    /**
     * Get marketplace insights
     */
    public function getMarketplaceInsights(array $params): ResponseInterface
    {
        return $this->get('/sell/analytics/v1/marketplace_insights', $params);
    }

    /**
     * Bulk update inventory
     */
    public function bulkUpdateInventory(array $requests): ResponseInterface
    {
        return $this->post('/sell/inventory/v1/bulk_update_price_quantity', [
            'requests' => $requests
        ]);
    }

    /**
     * Get bulk update status
     */
    public function getBulkUpdateStatus(string $taskId): ResponseInterface
    {
        return $this->get("/sell/inventory/v1/bulk_update_price_quantity/{$taskId}");
    }

    /**
     * Get category tree
     */
    public function getCategoryTree(string $categoryTreeId): ResponseInterface
    {
        return $this->get("/commerce/taxonomy/v1/category_tree/{$categoryTreeId}");
    }

    /**
     * Get category suggestions
     */
    public function getCategorySuggestions(string $query, string $categoryTreeId): ResponseInterface
    {
        return $this->get("/commerce/taxonomy/v1/category_tree/{$categoryTreeId}/get_category_suggestions", [
            'q' => $query
        ]);
    }
}