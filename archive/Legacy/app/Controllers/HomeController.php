<?php

namespace DGLab\Controllers;

use DGLab\Core\BaseController;
use DGLab\Core\Response;

class HomeController extends BaseController
{
    public function index(): Response
    {
        return $this->view('home');
    }
}
