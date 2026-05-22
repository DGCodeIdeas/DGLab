<?php

namespace DGLab\Core;

/**
 * Class ResponseFactory
 */
class ResponseFactory implements \DGLab\Core\Contracts\ResponseFactoryInterface
{
    public function create(string $content = '', int $status = 200, array $headers = []): \DGLab\Core\Response
    {
        return new Response($content, $status, $headers);
    }

    public function json(array $data, int $status = 200, array $headers = []): \DGLab\Core\Response
    {
        return Response::json($data, $status, $headers);
    }

    public function redirect(string $url, int $status = 302, array $headers = []): \DGLab\Core\Response
    {
        return Response::redirect($url, $status, $headers);
    }
}
