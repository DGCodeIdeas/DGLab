<?php

namespace DGLab\Core;

/**
 * Request class to handle incoming HTTP requests
 */
class Request
{
    private array $query;
    private array $post;
    private array $cookies;
    private array $files;
    private array $server;
    private array $routeParams = [];
    private ?string $body = null;
    private ?array $jsonBody = null;

    public function __construct(
        array $query = [],
        array $post = [],
        array $cookies = [],
        array $server = [],
        array $files = []
    ) {
        $this->query = $query;
        $this->post = $post;
        $this->cookies = $cookies;
        $this->server = $server;
        $this->files = $files;
    }

    public static function createFromGlobals(): self
    {
        return new self(
            $_GET,
            $_POST,
            $_COOKIE,
            $_SERVER,
            $_FILES
        );
    }

    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function getPath(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return '/' . trim($path, '/');
    }

    public function getUri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function getScheme(): string
    {
        return (isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ? 'https' : 'http';
    }

    public function getHost(): string
    {
        return $this->server['HTTP_HOST'] ?? 'localhost';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

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

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '' && $value !== [];
    }

    public function getServer(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function getHeader(string $name): ?string
    {
        $name = strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$name])) {
            return $this->server[$name];
        }
        if (isset($this->server['HTTP_' . $name])) {
            return $this->server['HTTP_' . $name];
        }
        return null;
    }

    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }
        if (isset($this->server['CONTENT_TYPE'])) $headers['CONTENT-TYPE'] = $this->server['CONTENT_TYPE'];
        if (isset($this->server['CONTENT_LENGTH'])) $headers['CONTENT-LENGTH'] = $this->server['CONTENT_LENGTH'];
        return $headers;
    }

    public function getContentType(): ?string
    {
        return $this->getHeader('Content-Type');
    }

    public function isJson(): bool
    {
        $contentType = $this->getContentType();
        return $contentType && (strpos($contentType, 'application/json') !== false);
    }

    public function isAjax(): bool
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }

    public function expectsJson(): bool
    {
        $accept = $this->getHeader('Accept');
        if ($accept && (strpos($accept, 'application/json') !== false || strpos($accept, '*/*') !== false)) {
            return true;
        }
        return $this->isAjax() || $this->isJson();
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonBody === null && $this->isJson()) {
            $body = $this->getBody();
            if ($body) {
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->jsonBody = $data;
                }
            }
        }
        if ($key === null) return $this->jsonBody;
        return ($this->jsonBody[$key] ?? $default);
    }

    public function getBody(): ?string
    {
        if ($this->body !== null) return $this->body;
        $this->body = file_get_contents('php://input');
        return $this->body;
    }

    public function file(string $key): ?UploadedFile
    {
        if (!isset($this->files[$key])) return null;
        return new UploadedFile($this->files[$key]);
    }

    public function allFiles(): array
    {
        $files = [];
        foreach ($this->files as $key => $file) {
            $files[$key] = new UploadedFile($file);
        }
        return $files;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function getClientIp(): ?string
    {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($this->server[$header])) {
                $ips = explode(',', $this->server[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return null;
    }

    public function getUserAgent(): ?string
    {
        return $this->getHeader('User-Agent');
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function withRouteParams(array $params): self
    {
        $new = clone $this;
        $new->routeParams = $params;
        return $new;
    }

    public function validateCsrfToken(): bool
    {
        $token = $this->input('_token') ?? $this->getHeader('X-CSRF-Token');
        $sessionToken = $_SESSION['_csrf_token'] ?? null;
        if ($token === null || $sessionToken === null) return false;
        return hash_equals($sessionToken, $token);
    }

    public function getProtocolVersion(): string
    {
        return $this->server['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
    }

    public function isSecure(): bool
    {
        return (isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off');
    }
}
