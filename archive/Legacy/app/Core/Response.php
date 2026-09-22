<?php

namespace DGLab\Core;

/**
 * Response Class
 *
 * Handles HTTP responses, headers, cookies, and various response formats.
 */
class Response
{
    /**
     * HTTP status codes and phrases
     */
    private const STATUS_PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        416 => 'Requested Range Not Satisfiable',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        503 => 'Service Unavailable',
    ];

    /**
     * @var string Response content
     */
    protected string $content;

    /**
     * @var int HTTP status code
     */
    protected int $statusCode;

    /**
     * @var array HTTP headers
     */
    protected array $headers;

    /**
     * @var array Cookies to be set
     */
    protected array $cookies = [];

    /**
     * @var string HTTP protocol version
     */
    protected string $version = '1.1';

    /**
     * Constructor
     */
    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Create a JSON response
     */
    public static function json(array $data, int $statusCode = 200, array $headers = []): self
    {
        $content = json_encode($data);
        $headers['Content-Type'] = 'application/json';

        return new self($content, $statusCode, $headers);
    }

    /**
     * Create a redirect response
     */
    public static function redirect(string $url, int $statusCode = 302, array $headers = []): self
    {
        $headers['Location'] = $url;

        return new self('', $statusCode, $headers);
    }

    /**
     * Create a file download response
     */
    public static function download(string $file, ?string $name = null, array $headers = []): self
    {
        if (!file_exists($file)) {
            throw new \RuntimeException("File not found: {$file}");
        }

        $filename = $name ?: basename($file);
        $filesize = filesize($file);
        $mimeType = mime_content_type($file);

        if ($mimeType === false) {
            $mimeType = 'application/octet-stream';
        }

        $headers = array_merge([
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => $filesize,
            'Pragma' => 'public',
            'Expires' => '0',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ], $headers);

        $response = new self('', 200, $headers);
        $response->filePath = $file;

        return $response;
    }

    /**
     * Create a file stream response (inline)
     */
    public static function stream(string $file, ?string $name = null, array $headers = []): self
    {
        if (!file_exists($file)) {
            throw new \RuntimeException("File not found: {$file}");
        }

        $filename = $name ?: basename($file);
        $filesize = filesize($file);
        $mimeType = mime_content_type($file);

        if ($mimeType === false) {
            $mimeType = 'application/octet-stream';
        }

        $headers = array_merge([
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400',
        ], $headers);

        $response = new self('', 200, $headers);
        $response->filePath = $file;
        $response->fileSize = $filesize;
        $response->supportsRange = true;

        return $response;
    }

    /**
     * Create a no-content response
     */
    public static function noContent(): self
    {
        return new self('', 204);
    }

    /**
     * Set the status code
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;

        return $this;
    }

    /**
     * Get the status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the status phrase
     */
    public function getStatusPhrase(): string
    {
        return self::STATUS_PHRASES[$this->statusCode] ?? 'Unknown';
    }

    /**
     * Set a header
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Add a header (without replacing existing)
     */
    public function addHeader(string $name, string $value): self
    {
        if (!isset($this->headers[$name])) {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * Get a header value
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Get all headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Remove a header
     */
    public function removeHeader(string $name): self
    {
        unset($this->headers[$name]);

        return $this;
    }

    /**
     * Set the content
     */
    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get the content
     */
    public function getContent(): string
    {
        if (isset($this->filePath) && empty($this->content)) {
            return file_get_contents($this->filePath);
        }
        return $this->content;
    }

    /**
     * Set cache headers
     */
    public function setCache(array $options): self
    {
        if (isset($options['public'])) {
            $this->headers['Cache-Control'] = 'public';
        } elseif (isset($options['private'])) {
            $this->headers['Cache-Control'] = 'private';
        } elseif (isset($options['no_cache'])) {
            $this->headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        }

        if (isset($options['max_age'])) {
            $this->headers['Cache-Control'] .= ', max-age=' . $options['max_age'];
        }

        if (isset($options['expires'])) {
            $this->headers['Expires'] = gmdate('D, d M Y H:i:s GMT', $options['expires']);
        }

        if (isset($options['etag'])) {
            $this->headers['ETag'] = '"' . $options['etag'] . '"';
        }

        if (isset($options['last_modified'])) {
            $this->headers['Last-Modified'] = gmdate('D, d M Y H:i:s GMT', $options['last_modified']);
        }

        return $this;
    }

    /**
     * Set CORS headers
     */
    public function setCors(array $options): self
    {
        $origin = $options['origin'] ?? '*';
        $methods = $options['methods'] ?? 'GET, POST, PUT, DELETE, OPTIONS';
        $headers = $options['headers'] ?? 'Content-Type, Authorization, X-Requested-With';
        $credentials = $options['credentials'] ?? false;
        $maxAge = $options['max_age'] ?? 86400;

        $this->headers['Access-Control-Allow-Origin'] = $origin;
        $this->headers['Access-Control-Allow-Methods'] = $methods;
        $this->headers['Access-Control-Allow-Headers'] = $headers;
        $this->headers['Access-Control-Max-Age'] = $maxAge;

        if ($credentials) {
            $this->headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return $this;
    }

    /**
     * Set a cookie
     */
    public function setCookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        ?string $sameSite = 'Lax'
    ): self {
        $this->cookies[$name] = [
            'value' => $value,
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httpOnly' => $httpOnly,
            'sameSite' => $sameSite,
        ];

        return $this;
    }

    /**
     * Delete a cookie
     */
    public function deleteCookie(string $name, string $path = '/', ?string $domain = null): self
    {
        return $this->setCookie($name, '', time() - 3600, $path, $domain);
    }

    /**
     * Set the HTTP version
     */
    public function setProtocolVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Send the response
     */
    public function send(): void
    {
        $this->sendHeaders();
        $this->sendContent();
    }

    /**
     * Send headers
     */
    private function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        // Send status line
        $phrase = $this->getStatusPhrase();
        header("HTTP/{$this->version} {$this->statusCode} {$phrase}");

        // Send headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Send cookies
        foreach ($this->cookies as $name => $cookie) {
            $options = [
                'expires' => $cookie['expires'],
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httpOnly'],
            ];

            if ($cookie['sameSite'] !== null) {
                $options['samesite'] = $cookie['sameSite'];
            }

            setcookie($name, $cookie['value'], $options);
        }
    }

    /**
     * Send content
     */
    private function sendContent(): void
    {
        // Handle file download/stream
        if (isset($this->filePath)) {
            $this->sendFile();
            return;
        }

        echo $this->content;
    }

    /**
     * File path for download/stream
     */
    private ?string $filePath = null;

    /**
     * File size for range requests
     */
    private int $fileSize = 0;

    /**
     * Whether range requests are supported
     */
    private bool $supportsRange = false;

    /**
     * Send file with optional range support
     */
    private function sendFile(): void
    {
        if (!$this->supportsRange) {
            readfile($this->filePath);
            return;
        }

        // Handle range requests
        $range = $_SERVER['HTTP_RANGE'] ?? null;

        if ($range === null) {
            header("Content-Length: {$this->fileSize}");
            readfile($this->filePath);
            return;
        }

        // Parse range header
        if (!preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            return;
        }

        $start = $matches[1] !== '' ? (int) $matches[1] : 0;
        $end = $matches[2] !== '' ? (int) $matches[2] : $this->fileSize - 1;

        // Validate range
        if ($start > $end || $end >= $this->fileSize) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes */{$this->fileSize}");
            return;
        }

        $length = $end - $start + 1;

        header('HTTP/1.1 206 Partial Content');
        header("Content-Length: {$length}");
        header("Content-Range: bytes {$start}-{$end}/{$this->fileSize}");

        $fp = fopen($this->filePath, 'rb');
        fseek($fp, $start);

        $remaining = $length;
        while ($remaining > 0 && !feof($fp)) {
            $chunkSize = min(8192, $remaining);
            echo fread($fp, $chunkSize);
            $remaining -= $chunkSize;
            flush();
        }

        fclose($fp);
    }

    /**
     * Check if response is a redirect
     */
    public function isRedirect(): bool
    {
        return in_array($this->statusCode, [301, 302, 303, 307, 308], true);
    }

    /**
     * Check if response is successful
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if response is a client error
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if response is a server error
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Check if response is informational
     */
    public function isInformational(): bool
    {
        return $this->statusCode >= 100 && $this->statusCode < 200;
    }

    /**
     * Check if response is OK (200)
     */
    public function isOk(): bool
    {
        return $this->statusCode === 200;
    }

    /**
     * Check if response is not found (404)
     */
    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    /**
     * Check if response is forbidden (403)
     */
    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }

    /**
     * Check if response is unauthorized (401)
     */
    public function isUnauthorized(): bool
    {
        return $this->statusCode === 401;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }
}
