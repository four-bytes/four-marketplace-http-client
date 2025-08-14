<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Client;

use Four\MarketplaceHttp\Factory\HttpClientFactoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Amazon SP-API HTTP client with marketplace-specific optimizations
 *
 * Provides methods optimized for Amazon Selling Partner API interactions
 * with proper authentication, rate limiting, and error handling.
 */
class AmazonClient extends MarketplaceClient
{
    public function getMarketplace(): string
    {
        return 'amazon';
    }

    protected function createHttpClient(HttpClientFactoryInterface $factory): HttpClientInterface
    {
        return $factory->createAmazonClient($this->config);
    }

    /**
     * Get orders from the Orders API
     */
    public function getOrders(array $params = []): ResponseInterface
    {
        $requiredParams = [
            'MarketplaceIds' => $params['MarketplaceIds'] ?? ['A1PA6795UKMFR9'], // Default to DE
            'CreatedAfter' => $params['CreatedAfter'] ?? (new \DateTimeImmutable('-30 days'))->format('c')
        ];

        $allParams = array_merge($requiredParams, $params);

        return $this->get('/orders/v0/orders', $allParams);
    }

    /**
     * Get a specific order by Amazon Order ID
     */
    public function getOrder(string $orderId): ResponseInterface
    {
        return $this->get("/orders/v0/orders/{$orderId}");
    }

    /**
     * Get order items for a specific order
     */
    public function getOrderItems(string $orderId): ResponseInterface
    {
        return $this->get("/orders/v0/orders/{$orderId}/orderItems");
    }

    /**
     * Update inventory for a SKU
     */
    public function updateInventory(string $sku, array $inventoryData): ResponseInterface
    {
        return $this->patch("/listings/2021-08-01/items/{$this->config->baseUri}/sku/{$sku}", [
            'productType' => $inventoryData['productType'] ?? 'PRODUCT',
            'patches' => [
                [
                    'op' => 'replace',
                    'path' => '/attributes/fulfillment_availability',
                    'value' => [
                        [
                            'fulfillment_channel_code' => 'AMAZON_NA',
                            'quantity' => $inventoryData['quantity'] ?? 0
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Create a feed for bulk operations
     */
    public function createFeed(string $feedType, string $content, array $marketplaceIds): ResponseInterface
    {
        return $this->post('/feeds/2021-06-30/feeds', [
            'feedType' => $feedType,
            'marketplaceIds' => $marketplaceIds,
            'inputFeedDocumentId' => $this->uploadFeedDocument($content)
        ]);
    }

    /**
     * Get feed processing status
     */
    public function getFeed(string $feedId): ResponseInterface
    {
        return $this->get("/feeds/2021-06-30/feeds/{$feedId}");
    }

    /**
     * Get report by report ID
     */
    public function getReport(string $reportId): ResponseInterface
    {
        return $this->get("/reports/2021-06-30/reports/{$reportId}");
    }

    /**
     * Create a report
     */
    public function createReport(string $reportType, array $marketplaceIds, array $options = []): ResponseInterface
    {
        $reportData = [
            'reportType' => $reportType,
            'marketplaceIds' => $marketplaceIds
        ];

        if (isset($options['dataStartTime'])) {
            $reportData['dataStartTime'] = $options['dataStartTime'];
        }

        if (isset($options['dataEndTime'])) {
            $reportData['dataEndTime'] = $options['dataEndTime'];
        }

        return $this->post('/reports/2021-06-30/reports', $reportData);
    }

    /**
     * Get catalog item details
     */
    public function getCatalogItem(string $asin, array $marketplaceIds, array $includedData = []): ResponseInterface
    {
        $params = [
            'marketplaceIds' => implode(',', $marketplaceIds)
        ];

        if (!empty($includedData)) {
            $params['includedData'] = implode(',', $includedData);
        }

        return $this->get("/catalog/2022-04-01/items/{$asin}", $params);
    }

    /**
     * Search catalog items
     */
    public function searchCatalogItems(array $params): ResponseInterface
    {
        $requiredParams = [
            'marketplaceIds' => $params['marketplaceIds'] ?? ['A1PA6795UKMFR9']
        ];

        $allParams = array_merge($requiredParams, $params);

        return $this->get('/catalog/2022-04-01/items', $allParams);
    }

    /**
     * Get FBA inventory summaries
     */
    public function getFbaInventorySummaries(array $params = []): ResponseInterface
    {
        return $this->get('/fba/inventory/v1/summaries', $params);
    }

    /**
     * Upload feed document (helper method)
     */
    private function uploadFeedDocument(string $content): string
    {
        // First, create feed document
        $createResponse = $this->post('/feeds/2021-06-30/documents', [
            'contentType' => 'text/tab-separated-values; charset=UTF-8'
        ]);

        $createData = $this->parseJsonResponse($createResponse);
        $uploadUrl = $createData['payload']['url'];
        $feedDocumentId = $createData['payload']['feedDocumentId'];

        // Upload content to the provided URL
        $uploadResponse = $this->httpClient->request('PUT', $uploadUrl, [
            'headers' => ['Content-Type' => 'text/tab-separated-values; charset=UTF-8'],
            'body' => $content
        ]);

        if (!$this->isSuccessful($uploadResponse)) {
            throw new \RuntimeException('Failed to upload feed document');
        }

        return $feedDocumentId;
    }

    /**
     * Download report document content
     */
    public function downloadReportDocument(string $reportDocumentId): string
    {
        // Get report document details
        $documentResponse = $this->get("/reports/2021-06-30/documents/{$reportDocumentId}");
        $documentData = $this->parseJsonResponse($documentResponse);
        
        $downloadUrl = $documentData['payload']['url'];

        // Download the actual report content
        $contentResponse = $this->httpClient->request('GET', $downloadUrl);
        
        if (!$this->isSuccessful($contentResponse)) {
            throw new \RuntimeException('Failed to download report document');
        }

        return $contentResponse->getContent();
    }
}