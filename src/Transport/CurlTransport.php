<?php

declare(strict_types=1);

namespace Four\MarketplaceHttp\Transport;

/**
 * cURL-based HTTP transport
 *
 * Uses PHP's cURL extension for HTTP requests. Provides the most features
 * and is the preferred transport for production environments.
 */
class CurlTransport implements TransportInterface
{
    private const SUPPORTED_METHODS = [
        'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE'
    ];

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

        $curl = curl_init();
        
        try {
            $this->configureCurl($curl, $method, $url, $options);
            
            $response = curl_exec($curl);
            
            if ($response === false) {
                $error = curl_error($curl);
                $errorCode = curl_errno($curl);
                
                throw match ($errorCode) {
                    CURLE_OPERATION_TIMEDOUT => TransportException::timeout(
                        $this->getType(), 
                        $options['timeout'] ?? 30.0
                    ),
                    CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST => 
                        TransportException::connectionFailed($this->getType(), $error),
                    CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CERTPROBLEM, CURLE_SSL_CIPHER => 
                        TransportException::sslError($this->getType(), $error),
                    default => new TransportException(
                        "cURL error: {$error}",
                        $errorCode,
                        null,
                        $this->getType()
                    )
                };
            }

            $info = curl_getinfo($curl);
            $statusCode = (int) $info['http_code'];
            
            // Split response into headers and body
            $headerSize = $info['header_size'];
            $headers = $this->parseResponseHeaders(substr($response, 0, $headerSize));
            $body = substr($response, $headerSize);

            // Add transport-specific info
            $info['transport_type'] = $this->getType();
            
            return new TransportResponse($statusCode, $headers, $body, $info);

        } finally {
            curl_close($curl);
        }
    }

    public function getType(): string
    {
        return 'curl';
    }

    public function isAvailable(): bool
    {
        return extension_loaded('curl') && function_exists('curl_init');
    }

    public function getCapabilities(): array
    {
        $capabilities = [
            'supported_methods' => self::SUPPORTED_METHODS,
            'supports_ssl' => true,
            'supports_redirects' => true,
            'supports_cookies' => true,
            'supports_streaming' => true,
            'supports_async' => false, // Would require curl_multi_*
            'supports_http2' => false
        ];

        if ($this->isAvailable()) {
            $version = curl_version();
            $capabilities['curl_version'] = $version['version'] ?? 'unknown';
            $capabilities['ssl_version'] = $version['ssl_version'] ?? 'unknown';
            $capabilities['protocols'] = $version['protocols'] ?? [];
            $capabilities['supports_http2'] = in_array('http2', $version['features'] ?? [], true);
        }

        return $capabilities;
    }

    /**
     * Configure cURL handle for the request
     *
     * @param resource $curl cURL handle
     * @param array<string, mixed> $options Request options
     */
    private function configureCurl($curl, string $method, string $url, array $options): void
    {
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT => (int) ($options['timeout'] ?? 30),
            CURLOPT_CONNECTTIMEOUT => (int) ($options['connect_timeout'] ?? 10),
            CURLOPT_USERAGENT => $options['user_agent'] ?? 'Four-MarketplaceHttp/1.0 (cURL)',
            CURLOPT_FOLLOWLOCATION => $options['follow_redirects'] ?? true,
            CURLOPT_MAXREDIRS => $options['max_redirects'] ?? 20,
            CURLOPT_SSL_VERIFYPEER => $options['verify_ssl'] ?? true,
            CURLOPT_SSL_VERIFYHOST => $options['verify_ssl'] ?? true ? 2 : 0,
            CURLOPT_ENCODING => '', // Accept all supported encodings
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ];

        // Enable HTTP/2 if supported and requested
        if ($options['http_version'] === '2.0' && $this->supportsHttp2()) {
            $curlOptions[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
        }

        // Handle request body
        $body = $options['body'] ?? '';
        if (!empty($body) && !in_array($method, ['GET', 'HEAD'], true)) {
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        // Handle headers
        $headers = $options['headers'] ?? [];
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
            $curlOptions[CURLOPT_HTTPHEADER] = $headerLines;
        }

        // Handle authentication
        if (isset($options['auth'])) {
            $auth = $options['auth'];
            if (isset($auth['username'], $auth['password'])) {
                $curlOptions[CURLOPT_USERPWD] = $auth['username'] . ':' . $auth['password'];
                $curlOptions[CURLOPT_HTTPAUTH] = $auth['type'] ?? CURLAUTH_BASIC;
            }
        }

        // Handle proxy settings
        if (isset($options['proxy'])) {
            $proxy = $options['proxy'];
            $curlOptions[CURLOPT_PROXY] = $proxy['host'] . ':' . $proxy['port'];
            
            if (isset($proxy['username'], $proxy['password'])) {
                $curlOptions[CURLOPT_PROXYUSERPWD] = $proxy['username'] . ':' . $proxy['password'];
            }
        }

        // SSL/TLS options
        if (isset($options['ca_bundle'])) {
            $curlOptions[CURLOPT_CAINFO] = $options['ca_bundle'];
        }

        if (isset($options['client_cert'])) {
            $curlOptions[CURLOPT_SSLCERT] = $options['client_cert'];
        }

        if (isset($options['client_key'])) {
            $curlOptions[CURLOPT_SSLKEY] = $options['client_key'];
        }

        // Progress callback for large uploads/downloads
        if (isset($options['progress_callback']) && is_callable($options['progress_callback'])) {
            $curlOptions[CURLOPT_NOPROGRESS] = false;
            $curlOptions[CURLOPT_PROGRESSFUNCTION] = $options['progress_callback'];
        }

        // Debug mode
        if ($options['debug'] ?? false) {
            $curlOptions[CURLOPT_VERBOSE] = true;
            if (isset($options['debug_file'])) {
                $curlOptions[CURLOPT_STDERR] = fopen($options['debug_file'], 'a');
            }
        }

        curl_setopt_array($curl, $curlOptions);
    }

    /**
     * Parse response headers from cURL header string
     *
     * @return array<string, string|array<string>>
     */
    private function parseResponseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", trim($headerString));

        foreach ($lines as $line) {
            if (empty($line) || !str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
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

        return $headers;
    }

    /**
     * Check if HTTP/2 is supported
     */
    private function supportsHttp2(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $version = curl_version();
        return in_array('http2', $version['features'] ?? [], true);
    }
}