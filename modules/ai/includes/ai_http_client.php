<?php
/**
 * AI HTTP Client
 * 
 * Centralized HTTP client for AI provider API calls.
 * Handles cURL requests with timeout, SSL verification, automatic CA bundle resolution,
 * automatic JSON payload formatting, response decoding, and clear error reporting.
 */

class AIHttpClient
{
    private int $timeout;
    private bool $verifySsl;
    private ?string $caBundlePath = null;

    public function __construct(int $timeout = 60, bool $verifySsl = true)
    {
        $this->timeout = $timeout;
        $this->verifySsl = $verifySsl;
        $this->caBundlePath = $this->detectCaBundle();
    }

    /**
     * Detect a valid CA bundle path on the system.
     */
    private function detectCaBundle(): ?string
    {
        $candidates = [
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile'),
            'C:/xampp/phpMyAdmin/vendor/composer/ca-bundle/res/cacert.pem',
            'C:/xampp/perl/vendor/lib/Mozilla/CA/cacert.pem',
            'C:/xampp/apache/bin/curl-ca-bundle.crt',
            'C:/xampp/php/extras/ssl/cacert.pem',
            dirname(__DIR__, 3) . '/cacert.pem',
        ];

        foreach ($candidates as $path) {
            if (!empty($path) && file_exists($path) && is_readable($path)) {
                return str_replace('\\', '/', $path);
            }
        }

        return null;
    }

    /**
     * Send a POST request.
     * Supports both signature forms:
     *   post($url, $body, $headers)
     *   post($url, $headers, $body)
     *
     * @param string $url
     * @param mixed $bodyOrHeaders
     * @param mixed $headersOrBody
     * @return array
     */
    public function post(string $url, $bodyOrHeaders = null, $headersOrBody = []): array
    {
        $body = null;
        $headers = [];

        // Check if 2nd arg is headers (array of strings like ['Authorization: ...']) and 3rd is body string
        if (is_array($bodyOrHeaders) && !empty($bodyOrHeaders) && isset($bodyOrHeaders[0]) && is_string($bodyOrHeaders[0]) && strpos($bodyOrHeaders[0], ':') !== false && is_string($headersOrBody)) {
            $headers = $bodyOrHeaders;
            $body = $headersOrBody;
        } else {
            $body = $bodyOrHeaders;
            $headers = is_array($headersOrBody) ? $headersOrBody : [];
        }

        return $this->request('POST', $url, $headers, $body);
    }

    /**
     * Send a GET request.
     */
    public function get(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, $headers);
    }

    /**
     * Execute an HTTP request.
     */
    private function request(string $method, string $url, array $headers = [], $body = null): array
    {
        $ch = curl_init();

        // Ensure headers array is clean
        $headerList = [];
        $hasContentType = false;
        foreach ($headers as $k => $v) {
            if (is_int($k)) {
                $headerList[] = $v;
                if (stripos($v, 'Content-Type:') === 0) {
                    $hasContentType = true;
                }
            } else {
                $headerList[] = "$k: $v";
                if (stripos($k, 'Content-Type') === 0) {
                    $hasContentType = true;
                }
            }
        }

        // Format body for POST
        $postData = null;
        if ($method === 'POST') {
            if (is_array($body) || is_object($body)) {
                $postData = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (!$hasContentType) {
                    $headerList[] = 'Content-Type: application/json';
                }
            } elseif (is_string($body)) {
                $postData = $body;
                if (!$hasContentType && (str_starts_with(trim($body), '{') || str_starts_with(trim($body), '['))) {
                    $headerList[] = 'Content-Type: application/json';
                }
            }
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerList);

        // SSL options
        if ($this->caBundlePath) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->caBundlePath);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
        } else {
            // If on local Windows and no CA bundle is present, disable peer verify so requests succeed
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($postData !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            }
        }

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        // Parse JSON data if response exists
        $data = null;
        if ($response !== false && $response !== '') {
            $data = json_decode($response, true);
        }

        $isSuccess = ($curlErrno === 0 && $statusCode >= 200 && $statusCode < 300);

        // Extract error message if not success
        $errorMsg = null;
        if (!$isSuccess) {
            if ($curlErrno !== 0) {
                $errorMsg = "cURL Error ({$curlErrno}): {$curlError}";
            } elseif (is_array($data)) {
                if (isset($data['error']['message']) && is_string($data['error']['message'])) {
                    $errorMsg = $data['error']['message'];
                } elseif (isset($data['error']) && is_string($data['error'])) {
                    $errorMsg = $data['error'];
                } elseif (isset($data['message']) && is_string($data['message'])) {
                    $errorMsg = $data['message'];
                } elseif (isset($data['detail']) && is_string($data['detail'])) {
                    $errorMsg = $data['detail'];
                } else {
                    $errorMsg = "API returned HTTP status {$statusCode}";
                }
            } else {
                $errorMsg = !empty($response) ? "HTTP {$statusCode}: " . substr(strip_tags($response), 0, 200) : "HTTP {$statusCode} error";
            }
        }

        return [
            'status'     => $statusCode,
            'body'       => $response ?: '',
            'data'       => $data,
            'error'      => $errorMsg,
            'errno'      => $curlErrno,
            'success'    => $isSuccess,
        ];
    }
}

