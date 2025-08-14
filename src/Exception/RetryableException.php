<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Exception;

/**
 * Exception for errors that can be retried
 *
 * This exception indicates that an HTTP request failed due to a temporary
 * issue and the operation can be retried after a delay.
 */
class RetryableException extends HttpClientException
{
    public function __construct(
        string $message = 'Retryable error occurred',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $marketplace = null,
        ?string $operation = null,
        private readonly int $currentAttempt = 1,
        private readonly int $maxAttempts = 3,
        private readonly float $nextRetryDelay = 1.0
    ) {
        parent::__construct($message, $code, $previous, $marketplace, $operation);
    }

    /**
     * Get the current retry attempt number
     */
    public function getCurrentAttempt(): int
    {
        return $this->currentAttempt;
    }

    /**
     * Get the maximum number of retry attempts allowed
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Get the delay before the next retry attempt
     */
    public function getNextRetryDelay(): float
    {
        return $this->nextRetryDelay;
    }

    /**
     * Check if more retries are available
     */
    public function hasRetriesLeft(): bool
    {
        return $this->currentAttempt < $this->maxAttempts;
    }

    /**
     * Create exception for network timeout
     */
    public static function networkTimeout(
        ?string $marketplace = null,
        ?string $operation = null,
        int $currentAttempt = 1,
        int $maxAttempts = 3,
        float $nextRetryDelay = 1.0
    ): self {
        return new self(
            'Network timeout occurred',
            0,
            null,
            $marketplace,
            $operation,
            $currentAttempt,
            $maxAttempts,
            $nextRetryDelay
        );
    }

    /**
     * Create exception for server error
     */
    public static function serverError(
        int $statusCode,
        ?string $marketplace = null,
        ?string $operation = null,
        int $currentAttempt = 1,
        int $maxAttempts = 3,
        float $nextRetryDelay = 1.0
    ): self {
        return new self(
            "Server error: HTTP {$statusCode}",
            $statusCode,
            null,
            $marketplace,
            $operation,
            $currentAttempt,
            $maxAttempts,
            $nextRetryDelay
        );
    }

    /**
     * Create exception from previous exception with retry context
     */
    public static function fromException(
        \Throwable $previous,
        ?string $marketplace = null,
        ?string $operation = null,
        int $currentAttempt = 1,
        int $maxAttempts = 3,
        float $nextRetryDelay = 1.0
    ): self {
        return new self(
            "Retryable error: {$previous->getMessage()}",
            $previous->getCode(),
            $previous,
            $marketplace,
            $operation,
            $currentAttempt,
            $maxAttempts,
            $nextRetryDelay
        );
    }
}