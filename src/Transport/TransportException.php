<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Transport;

/**
 * Exception thrown by transport layer
 */
class TransportException extends \Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $transportType = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get transport type that caused the exception
     */
    public function getTransportType(): ?string
    {
        return $this->transportType;
    }

    /**
     * Create exception for connection timeout
     */
    public static function timeout(string $transportType, float $timeout): self
    {
        return new self(
            "Request timeout after {$timeout} seconds",
            28, // CURLE_OPERATION_TIMEDOUT
            null,
            $transportType
        );
    }

    /**
     * Create exception for connection failure
     */
    public static function connectionFailed(string $transportType, string $reason): self
    {
        return new self(
            "Connection failed: {$reason}",
            7, // CURLE_COULDNT_CONNECT
            null,
            $transportType
        );
    }

    /**
     * Create exception for SSL/TLS errors
     */
    public static function sslError(string $transportType, string $reason): self
    {
        return new self(
            "SSL/TLS error: {$reason}",
            35, // CURLE_SSL_CONNECT_ERROR
            null,
            $transportType
        );
    }

    /**
     * Create exception for invalid URL
     */
    public static function invalidUrl(string $transportType, string $url): self
    {
        return new self(
            "Invalid URL: {$url}",
            3, // CURLE_URL_MALFORMAT
            null,
            $transportType
        );
    }

    /**
     * Create exception for unsupported method
     */
    public static function unsupportedMethod(string $transportType, string $method): self
    {
        return new self(
            "Unsupported HTTP method: {$method}",
            0,
            null,
            $transportType
        );
    }

    /**
     * Create exception for transport not available
     */
    public static function notAvailable(string $transportType): self
    {
        return new self(
            "Transport not available: {$transportType}",
            0,
            null,
            $transportType
        );
    }
}