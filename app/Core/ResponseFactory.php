<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\ResponseFactoryInterface;

/**
 * Class ResponseFactory
 */
class ResponseFactory implements ResponseFactoryInterface
{
    public function create(string $content = '', int $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers);
    }

    public function json(array $data, int $status = 200, array $headers = []): Response
    {
        return Response::json($data, $status, $headers);
    }

    public function redirect(string $url, int $status = 302, array $headers = []): Response
    {
        return Response::redirect($url, $status, $headers);
    }
}
