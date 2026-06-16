<?php

namespace DGLab\Controllers;

use DGLab\Core\BaseController;
use DGLab\Core\Response;

class TestController extends BaseController
{
    public function morph(): Response
    {
        return $this->view('test.morph');
    }
}
