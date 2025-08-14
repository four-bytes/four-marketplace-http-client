<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Transport;

/**
 * Stream context HTTP transport
 *
 * Uses PHP's stream_context_create() and file_get_contents() for HTTP requests.
 * This is useful for simple requests and environments where cURL is not available.
 */
class StreamTransport implements TransportInterface
{
    private const SUPPORTED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    public function request(string $method, string $url, array $options = []): TransportResponse
    {
        if (!$this->isAvailable()) {
            throw TransportException::notAvailable($this->getType());
        }

        if (!in_array(strtoupper($method), self::SUPPORTED_METHODS, true)) {
            throw TransportException::unsupportedMethod($this->getType(), $method);
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw TransportException::invalidUrl($this->getType(), $url);
        }

        $startTime = microtime(true);

        try {
            $context = $this->createStreamContext($method, $options);
            
            // Capture response headers using $http_response_header
            $responseBody = file_get_contents($url, false, $context);
            $endTime = microtime(true);

            if ($responseBody === false) {
                $error = error_get_last();
                $errorMessage = $error['message'] ?? 'Unknown stream error';
                
                if (str_contains($errorMessage, 'timed out')) {
                    $timeout = $options['timeout'] ?? 30.0;
                    throw TransportException::timeout($this->getType(), $timeout);
                }
                
                throw TransportException::connectionFailed($this->getType(), $errorMessage);
            }

            // Parse response headers (available in global $http_response_header)
            global $http_response_header;
            $headers = $this->parseResponseHeaders($http_response_header ?? []);
            
            // Extract status code from first header line
            $statusCode = $this->parseStatusCode($http_response_header[0] ?? '');

            $info = [
                'effective_url' => $url,
                'total_time' => $endTime - $startTime,
                'transport_type' => $this->getType()
            ];

            return new TransportResponse($statusCode, $headers, $responseBody, $info);

        } catch (TransportException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new TransportException(
                "Stream transport error: {$e->getMessage()}",
                $e->getCode(),
                $e,
                $this->getType()
            );
        }
    }

    public function getType(): string
    {
        return 'stream';
    }

    public function isAvailable(): bool
    {
        return function_exists('file_get_contents') && 
               function_exists('stream_context_create') &&
               ini_get('allow_url_fopen') == '1';
    }

    public function getCapabilities(): array
    {
        return [
            'supported_methods' => self::SUPPORTED_METHODS,
            'supports_ssl' => $this->supportsSsl(),
            'supports_redirects' => true,
            'supports_cookies' => false,
            'supports_streaming' => false,
            'supports_async' => false,
            'max_redirects' => 20
        ];
    }

    /**
     * Create stream context for HTTP request
     */
    private function createStreamContext(string $method, array $options): resource
    {
        $headers = $options['headers'] ?? [];
        $body = $options['body'] ?? '';
        $timeout = $options['timeout'] ?? 30.0;
        $userAgent = $options['user_agent'] ?? 'Four-MarketplaceHttp/1.0 (Stream)';
        $followRedirects = $options['follow_redirects'] ?? true;
        $maxRedirects = $options['max_redirects'] ?? 20;

        // Build HTTP context options
        $contextOptions = [
            'http' => [
                'method' => strtoupper($method),
                'timeout' => $timeout,
                'user_agent' => $userAgent,
                'follow_location' => $followRedirects ? 1 : 0,
                'max_redirects' => $maxRedirects,
                'ignore_errors' => true, // Don't throw on HTTP errors, let us handle them
                'protocol_version' => '1.1'
            ]
        ];

        // Add request body for non-GET requests
        if (!empty($body) && $method !== 'GET' && $method !== 'HEAD') {
            $contextOptions['http']['content'] = $body;
        }

        // Add headers
        if (!empty($headers)) {
            $headerLines = [];
            foreach ($headers as $name => $value) {
                if (is_array($value)) {
                    foreach ($value as $val) {
                        $headerLines[] = "{$name}: {$val}";
                    }
                } else {
                    $headerLines[] = "{$name}: {$value}";
                }
            }
            $contextOptions['http']['header'] = implode("\r\n", $headerLines);
        }

        // SSL context options
        if (str_starts_with(strtolower($url ?? ''), 'https://')) {
            $contextOptions['ssl'] = [
                'verify_peer' => $options['verify_ssl'] ?? true,
                'verify_peer_name' => $options['verify_ssl'] ?? true,
                'allow_self_signed' => $options['allow_self_signed'] ?? false
            ];

            // Add custom CA bundle if specified
            if (isset($options['ca_bundle'])) {
                $contextOptions['ssl']['cafile'] = $options['ca_bundle'];
            }
        }

        return stream_context_create($contextOptions);
    }

    /**
     * Parse response headers from $http_response_header
     *
     * @param array<string> $rawHeaders
     * @return array<string, string|array<string>>
     */
    private function parseResponseHeaders(array $rawHeaders): array
    {
        $headers = [];

        foreach ($rawHeaders as $header) {
            if (str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $name = trim($name);
                $value = trim($value);

                // Handle multiple headers with same name
                if (isset($headers[$name])) {
                    if (is_array($headers[$name])) {
                        $headers[$name][] = $value;
                    } else {
                        $headers[$name] = [$headers[$name], $value];
                    }
                } else {
                    $headers[$name] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * Parse HTTP status code from response status line
     */
    private function parseStatusCode(string $statusLine): int
    {
        // Status line format: "HTTP/1.1 200 OK"
        if (preg_match('/HTTP\/[\d\.]+\s+(\d+)/', $statusLine, $matches)) {
            return (int) $matches[1];
        }

        return 0; // Unknown status
    }

    /**
     * Check if SSL/TLS is supported
     */
    private function supportsSsl(): bool
    {
        return in_array('https', stream_get_wrappers(), true);
    }
}