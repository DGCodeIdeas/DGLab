<?php

namespace DGLab\Events\Routing;

use DGLab\Core\BaseEvent;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class RequestHandled extends BaseEvent
{
    public function __construct(public Request $request, public Response $response)
    {
    }
}
