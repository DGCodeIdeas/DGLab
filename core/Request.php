<?php

namespace DGLab\Core;

class Request
{
    /**
     * The request path.
     *
     * @var string
     */
    protected string $path;

    /**
     * The request method.
     *
     * @var string
     */
    protected string $method;

    /**
     * Create a new request instance.
     */
    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = $this->extractPath();
    }

    /**
     * Extract the path from the request.
     *
     * @return string
     */
    protected function extractPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $position = strpos($uri, '?');

        if ($position === false) {
            return $uri;
        }

        return substr($uri, 0, $position);
    }

    /**
     * Get the request path.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the request method.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return strtoupper($this->method);
    }

    /**
     * Get input data from GET or POST.
     *
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $data = array_merge($_GET, $_POST);

        if ($this->isJson()) {
            $json = json_decode(file_get_contents('php://input'), true);
            if (is_array($json)) {
                $data = array_merge($data, $json);
            }
        }

        if (is_null($key)) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /**
     * Determine if the request is sending JSON.
     *
     * @return bool
     */
    public function isJson(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($contentType, 'application/json');
    }

    /**
     * Create a request instance from globals.
     *
     * @return static
     */
    public static function capture(): static
    {
        return new static();
    }
}
