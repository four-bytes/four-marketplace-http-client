<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Exception;

/**
 * Base exception for HTTP client operations
 *
 * All exceptions thrown by the Four\MarketplaceHttp library extend this base exception,
 * providing a consistent exception hierarchy for error handling.
 */
class HttpClientException extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        protected readonly ?string $marketplace = null,
        protected readonly ?string $operation = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the marketplace associated with this exception
     */
    public function getMarketplace(): ?string
    {
        return $this->marketplace;
    }

    /**
     * Get the operation associated with this exception
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Create exception with marketplace context
     */
    public static function forMarketplace(
        string $marketplace,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $operation = null
    ): static {
        return new static($message, $code, $previous, $marketplace, $operation);
    }
}