<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\ResponseFactoryInterface;

/**
 * Class ResponseFactory
 */
class ResponseFactory implements ResponseFactoryInterface
{
    public function create(string  = '', int  = 200, array  = []): Response
    {
        return new Response(, , );
    }

    public function json(array , int  = 200, array  = []): Response
    {
        return Response::json(, , );
    }

    public function redirect(string , int  = 302, array  = []): Response
    {
        return Response::redirect(, , );
    }
}
