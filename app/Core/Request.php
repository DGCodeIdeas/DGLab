<?php
/**
 * DGLab HTTP Request
 * 
 * PSR-7 inspired HTTP request abstraction wrapping superglobals.
 * Provides immutable modification methods and comprehensive input handling.
 * 
 * @package DGLab\Core
 */

namespace DGLab\Core;

/**
 * Class Request
 * 
 * Represents an HTTP request with access to:
 * - Query parameters ($_GET)
 * - Post data ($_POST)
 * - Uploaded files ($_FILES)
 * - Server variables ($_SERVER)
 * - Headers
 * - JSON input
 * - Route parameters
 * - CSRF token validation
 */
class Request
{
    /**
     * Query parameters
     */
    private array $query;
    
    /**
     * Post data
     */
    private array $post;
    
    /**
     * Uploaded files
     */
    private array $files;
    
    /**
     * Server variables
     */
    private array $server;
    
    /**
     * Cookies
     */
    private array $cookies;
    
    /**
     * Route parameters
     */
    private array $routeParams = [];
    
    /**
     * Parsed JSON body
     */
    private ?array $jsonBody = null;
    
    /**
     * Request body content
     */
    private ?string $body = null;

    /**
     * Create a new Request instance
     */
    public function __construct(
        array $query = [],
        array $post = [],
        array $files = [],
        array $server = [],
        array $cookies = []
    ) {
        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->server = $server;
        $this->cookies = $cookies;
    }

    /**
     * Create a Request from PHP superglobals
     */
    public static function createFromGlobals(): self
    {
        return new self($_GET, $_POST, $_FILES, $_SERVER, $_COOKIE);
    }

    /**
     * Get the HTTP method
     */
    public function getMethod(): string
    {
        $method = $this->server['REQUEST_METHOD'] ?? 'GET';
        
        // Check for method override
        if ($method === 'POST') {
            $override = $this->post['_method'] ?? $this->getHeader('X-HTTP-Method-Override');
            if ($override) {
                $method = strtoupper($override);
            }
        }
        
        return $method;
    }

    /**
     * Get the request URI path
     */
    public function getPath(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        return '/' . ltrim($uri, '/');
    }

    /**
     * Get the full request URI
     */
    public function getUri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /**
     * Get the scheme (http or https)
     */
    public function getScheme(): string
    {
        if (isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return 'https';
        }
        
        if ($this->getHeader('X-Forwarded-Proto') === 'https') {
            return 'https';
        }
        
        return 'http';
    }

    /**
     * Get the host
     */
    public function getHost(): string
    {
        return $this->getHeader('Host') ?? $this->server['SERVER_NAME'] ?? 'localhost';
    }

    /**
     * Get the port
     */
    public function getPort(): ?int
    {
        $port = $this->server['SERVER_PORT'] ?? null;
        
        if ($port) {
            return (int) $port;
        }
        
        return null;
    }

    /**
     * Get the full URL
     */
    public function getUrl(): string
    {
        $scheme = $this->getScheme();
        $host = $this->getHost();
        $port = $this->getPort();
        $uri = $this->getUri();
        
        $url = "{$scheme}://{$host}";
        
        if ($port && (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443))) {
            $url .= ":{$port}";
        }
        
        $url .= $uri;
        
        return $url;
    }

    /**
     * Get a query parameter
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get all query parameters
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    /**
     * Get a post parameter
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Get all post parameters
     */
    public function allPost(): array
    {
        return $this->post;
    }

    /**
     * Get an input value (post or query)
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Get all input values
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    /**
     * Get only specified keys
     */
    public function only(array $keys): array
    {
        $result = [];
        $all = $this->all();
        
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $result[$key] = $all[$key];
            }
        }
        
        return $result;
    }

    /**
     * Get all except specified keys
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    /**
     * Check if a key exists in input
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Check if a key exists and is not empty
     */
    public function filled(string $key): bool
    {
        $value = $this->input($key);
        
        return $value !== null && $value !== '' && $value !== [];
    }

    /**
     * Get a header value
     */
    public function getHeader(string $name): ?string
    {
        $name = strtoupper(str_replace('-', '_', $name));
        
        // Try standard header format
        $key = 'HTTP_' . $name;
        if (isset($this->server[$key])) {
            return $this->server[$key];
        }
        
        // Try content-type special case
        if ($name === 'CONTENT_TYPE' && isset($this->server['CONTENT_TYPE'])) {
            return $this->server['CONTENT_TYPE'];
        }
        
        if ($name === 'CONTENT_LENGTH' && isset($this->server['CONTENT_LENGTH'])) {
            return $this->server['CONTENT_LENGTH'];
        }
        
        // Case-insensitive search
        foreach ($this->server as $key => $value) {
            if (strtoupper($key) === 'HTTP_' . $name) {
                return $value;
            }
        }
        
        return null;
    }

    /**
     * Get all headers
     */
    public function getHeaders(): array
    {
        $headers = [];
        
        foreach ($this->server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }
        
        if (isset($this->server['CONTENT_TYPE'])) {
            $headers['CONTENT-TYPE'] = $this->server['CONTENT_TYPE'];
        }
        
        if (isset($this->server['CONTENT_LENGTH'])) {
            $headers['CONTENT-LENGTH'] = $this->server['CONTENT_LENGTH'];
        }
        
        return $headers;
    }

    /**
     * Get the content type
     */
    public function getContentType(): ?string
    {
        return $this->getHeader('Content-Type');
    }

    /**
     * Check if request is JSON
     */
    public function isJson(): bool
    {
        $contentType = $this->getContentType();
        
        if ($contentType === null) {
            return false;
        }
        
        return strpos($contentType, 'application/json') !== false;
    }

    /**
     * Get parsed JSON body
     */
    public function json(): ?array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }
        
        if (!$this->isJson()) {
            return null;
        }
        
        $body = $this->getBody();
        
        if ($body === null || $body === '') {
            return null;
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        $this->jsonBody = $data;
        
        return $data;
    }

    /**
     * Get a value from JSON body
     */
    public function json(string $key, mixed $default = null): mixed
    {
        $json = $this->json();
        
        if ($json === null) {
            return $default;
        }
        
        return $json[$key] ?? $default;
    }

    /**
     * Get the raw request body
     */
    public function getBody(): ?string
    {
        if ($this->body !== null) {
            return $this->body;
        }
        
        $this->body = file_get_contents('php://input');
        
        return $this->body;
    }

    /**
     * Get an uploaded file
     */
    public function file(string $key): ?UploadedFile
    {
        if (!isset($this->files[$key])) {
            return null;
        }
        
        return new UploadedFile($this->files[$key]);
    }

    /**
     * Get all uploaded files
     */
    public function allFiles(): array
    {
        $files = [];
        
        foreach ($this->files as $key => $file) {
            $files[$key] = new UploadedFile($file);
        }
        
        return $files;
    }

    /**
     * Get a cookie value
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Get the client's IP address
     */
    public function getClientIp(): ?string
    {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            if (!empty($this->server[$header])) {
                $ips = explode(',', $this->server[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return null;
    }

    /**
     * Get the user agent
     */
    public function getUserAgent(): ?string
    {
        return $this->getHeader('User-Agent');
    }

    /**
     * Check if request is AJAX
     */
    public function isAjax(): bool
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Check if request expects JSON response
     */
    public function expectsJson(): bool
    {
        $accept = $this->getHeader('Accept');
        
        if ($accept === null) {
            return $this->isAjax() || $this->isJson();
        }
        
        return strpos($accept, 'application/json') !== false;
    }

    /**
     * Get a route parameter
     */
    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Get all route parameters
     */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    /**
     * Create a new instance with route parameters
     * 
     * @param array $params Route parameters
     * @return self New request instance
     */
    public function withRouteParams(array $params): self
    {
        $new = clone $this;
        $new->routeParams = $params;
        
        return $new;
    }

    /**
     * Validate CSRF token
     */
    public function validateCsrfToken(): bool
    {
        $token = $this->input('_token') ?? $this->getHeader('X-CSRF-Token');
        $sessionToken = $_SESSION['_csrf_token'] ?? null;
        
        if ($token === null || $sessionToken === null) {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }

    /**
     * Get the request protocol version
     */
    public function getProtocolVersion(): string
    {
        return $this->server['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
    }

    /**
     * Check if request is secure (HTTPS)
     */
    public function isSecure(): bool
    {
        return $this->getScheme() === 'https';
    }
}

/**
 * Uploaded File wrapper
 */
class UploadedFile
{
    private array $file;

    public function __construct(array $file)
    {
        $this->file = $file;
    }

    /**
     * Get the original filename
     */
    public function getClientOriginalName(): string
    {
        return $this->file['name'] ?? '';
    }

    /**
     * Get the file extension
     */
    public function getClientOriginalExtension(): string
    {
        $name = $this->getClientOriginalName();
        
        return pathinfo($name, PATHINFO_EXTENSION);
    }

    /**
     * Get the MIME type
     */
    public function getClientMimeType(): string
    {
        return $this->file['type'] ?? 'application/octet-stream';
    }

    /**
     * Get the temporary path
     */
    public function getPathname(): string
    {
        return $this->file['tmp_name'] ?? '';
    }

    /**
     * Get the file size
     */
    public function getSize(): int
    {
        return $this->file['size'] ?? 0;
    }

    /**
     * Get the error code
     */
    public function getError(): int
    {
        return $this->file['error'] ?? UPLOAD_ERR_NO_FILE;
    }

    /**
     * Check if upload was successful
     */
    public function isValid(): bool
    {
        return $this->getError() === UPLOAD_ERR_OK && is_uploaded_file($this->getPathname());
    }

    /**
     * Move the file to a new location
     */
    public function move(string $directory, ?string $name = null): bool
    {
        if (!$this->isValid()) {
            return false;
        }
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $filename = $name ?? $this->getClientOriginalName();
        $destination = rtrim($directory, '/') . '/' . $filename;
        
        return move_uploaded_file($this->getPathname(), $destination);
    }

    /**
     * Get the real MIME type (detected from content)
     */
    public function getMimeType(): ?string
    {
        $path = $this->getPathname();
        
        if (!file_exists($path)) {
            return null;
        }
        
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        
        return $finfo->file($path);
    }

    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        return strpos($this->getMimeType() ?? '', 'image/') === 0;
    }
}
