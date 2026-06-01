<?php

namespace DGLab\Events\Routing;

use DGLab\Core\BaseEvent;
use DGLab\Core\Request;
use DGLab\Core\Response;

class RequestHandled extends BaseEvent
{
    public function __construct(public Request $request, public Response $response)
    {
    }
}
