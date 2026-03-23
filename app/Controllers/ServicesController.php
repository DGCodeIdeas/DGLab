<?php

namespace DGLab\Controllers;

use DGLab\Core\BaseController;
use DGLab\Core\Response;

class ServicesController extends BaseController
{
    public function index(): Response
    {
        $services = [
            [
                'id' => 'epub-font-changer',
                'name' => 'EPUB Font Changer',
                'description' => 'Change fonts in EPUB e-books with open-source font families.',
                'icon' => 'fa-book',
                'supports_chunking' => true
            ]
        ];

        return $this->view('services/index', ['services' => $services]);
    }
}
