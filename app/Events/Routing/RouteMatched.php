<?php

namespace DGLab\Events\Routing;

use DGLab\Core\BaseEvent;
use DGLab\Core\Request;
use DGLab\Core\Route;

class RouteMatched extends BaseEvent
{
    public function __construct(public Route $route, public Request $request) {}
}
