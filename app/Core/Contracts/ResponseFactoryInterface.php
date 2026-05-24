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
    public function create(string  = "", int  = 200, array  = []): Response;

    /**
     * Create a JSON response
     */
    public function json(array , int  = 200, array  = []): Response;

    /**
     * Create a redirect response
     */
    public function redirect(string , int  = 302, array  = []): Response;
}
