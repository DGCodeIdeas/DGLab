<?php

namespace DGLab\Core\Contracts;

use DGLab\Core\Response;

/**
 * Interface ResponseFactoryInterface
 *
 * Defines the contract for creating HTTP responses.
 */
interface ResponseFactoryInterface
{
    /**
     * Create a standard HTML/text response
     */
    public function create(string $content = "", int $status = 200, array $headers = []): Response;

    /**
     * Create a JSON response
     */
    public function json(array $data, int $status = 200, array $headers = []): Response;

    /**
     * Create a redirect response
     */
    public function redirect(string $url, int $status = 302, array $headers = []): Response;
}
