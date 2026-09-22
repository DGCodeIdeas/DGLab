<?php

namespace DGLab\Core;

/**
 * Interface ResponseFactoryInterface
 */
interface ResponseFactoryInterface
{
    public function create(string $content = '', int $status = 200, array $headers = []): Response;
    public function json(array $data, int $status = 200, array $headers = []): Response;
    public function redirect(string $url, int $status = 302, array $headers = []): Response;
}
