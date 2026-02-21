<?php

namespace DGLab\Core;

class Response
{
    /**
     * The response content.
     *
     * @var mixed
     */
    protected mixed $content;

    /**
     * The response status code.
     *
     * @var int
     */
    protected int $status;

    /**
     * The response headers.
     *
     * @var array
     */
    protected array $headers = [];

    /**
     * Create a new response instance.
     *
     * @param mixed $content
     * @param int $status
     * @param array $headers
     */
    public function __construct(mixed $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }

    /**
     * Set the response status code.
     *
     * @param int $status
     * @return $this
     */
    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Set a response header.
     *
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function setHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Send the response.
     *
     * @return void
     */
    public function send(): void
    {
        // Set status code
        http_response_code($this->status);

        // Set headers
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }

        // Output content
        echo $this->content;
    }

    /**
     * Create a JSON response.
     *
     * @param mixed $data
     * @param int $status
     * @return static
     */
    public static function json(mixed $data, int $status = 200): static
    {
        return new static(json_encode($data), $status, ['Content-Type' => 'application/json']);
    }
}
