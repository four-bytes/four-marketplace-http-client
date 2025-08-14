<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Configuration;

/**
 * Configuration for retry strategies
 *
 * Defines when and how to retry failed HTTP requests, including
 * which status codes to retry, maximum attempts, and backoff strategies.
 */
readonly class RetryConfig
{
    /**
     * @param int $maxAttempts Maximum number of retry attempts
     * @param float $initialDelay Initial delay between retries in seconds
     * @param float $multiplier Backoff multiplier for exponential backoff
     * @param float $maxDelay Maximum delay between retries in seconds
     * @param array<int> $retryableStatusCodes HTTP status codes that should trigger retries
     * @param array<string> $retryableExceptions Exception class names that should trigger retries
     */
    public function __construct(
        public int $maxAttempts = 3,
        public float $initialDelay = 1.0,
        public float $multiplier = 2.0,
        public float $maxDelay = 60.0,
        public array $retryableStatusCodes = [429, 500, 502, 503, 504],
        public array $retryableExceptions = [
            'Symfony\Contracts\HttpClient\Exception\TransportException',
            'Symfony\Contracts\HttpClient\Exception\ServerException'
        ]
    ) {
        if ($maxAttempts < 0) {
            throw new \InvalidArgumentException('Max attempts must be non-negative');
        }
        
        if ($initialDelay < 0) {
            throw new \InvalidArgumentException('Initial delay must be non-negative');
        }
        
        if ($multiplier <= 0) {
            throw new \InvalidArgumentException('Multiplier must be positive');
        }
        
        if ($maxDelay < $initialDelay) {
            throw new \InvalidArgumentException('Max delay must be greater than or equal to initial delay');
        }
    }

    /**
     * Create default retry configuration
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Create conservative retry configuration for rate-limited APIs
     */
    public static function conservative(): self
    {
        return new self(
            maxAttempts: 2,
            initialDelay: 2.0,
            multiplier: 3.0,
            maxDelay: 30.0,
            retryableStatusCodes: [429, 500, 502, 503, 504]
        );
    }

    /**
     * Create aggressive retry configuration for robust APIs
     */
    public static function aggressive(): self
    {
        return new self(
            maxAttempts: 5,
            initialDelay: 0.5,
            multiplier: 1.5,
            maxDelay: 120.0,
            retryableStatusCodes: [429, 500, 502, 503, 504, 408, 409]
        );
    }

    /**
     * Create retry configuration for specific marketplace
     */
    public static function forMarketplace(string $marketplace): self
    {
        return match ($marketplace) {
            'amazon' => new self(
                maxAttempts: 3,
                initialDelay: 1.0,
                multiplier: 2.0,
                maxDelay: 30.0,
                retryableStatusCodes: [429, 500, 502, 503, 504]
            ),
            'ebay' => new self(
                maxAttempts: 3,
                initialDelay: 1.5,
                multiplier: 2.0,
                maxDelay: 45.0,
                retryableStatusCodes: [429, 500, 502, 503, 504]
            ),
            'discogs' => new self(
                maxAttempts: 2,
                initialDelay: 2.0,
                multiplier: 2.5,
                maxDelay: 60.0,
                retryableStatusCodes: [429, 500, 502, 503, 504]
            ),
            'bandcamp' => new self(
                maxAttempts: 1,
                initialDelay: 3.0,
                multiplier: 2.0,
                maxDelay: 10.0,
                retryableStatusCodes: [429, 500, 502, 503, 504]
            ),
            default => self::default()
        };
    }

    /**
     * Calculate delay for specific attempt number
     */
    public function calculateDelay(int $attempt): float
    {
        if ($attempt < 1) {
            return 0.0;
        }

        $delay = $this->initialDelay * ($this->multiplier ** ($attempt - 1));
        return min($delay, $this->maxDelay);
    }

    /**
     * Check if status code should trigger a retry
     */
    public function shouldRetryStatusCode(int $statusCode): bool
    {
        return in_array($statusCode, $this->retryableStatusCodes, true);
    }

    /**
     * Check if exception should trigger a retry
     */
    public function shouldRetryException(\Throwable $exception): bool
    {
        $exceptionClass = get_class($exception);
        
        foreach ($this->retryableExceptions as $retryableClass) {
            if ($exceptionClass === $retryableClass || is_subclass_of($exceptionClass, $retryableClass)) {
                return true;
            }
        }
        
        return false;
    }
}