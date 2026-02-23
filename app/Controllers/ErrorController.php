<?php
/**
 * DGLab PWA - Error Controller
 * 
 * Handles error pages.
 * 
 * @package DGLab\Controllers
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Controllers;

use DGLab\Core\Controller;

/**
 * ErrorController Class
 * 
 * Controller for error pages.
 */
class ErrorController extends Controller
{
    /**
     * 404 Not Found page
     * 
     * @return void
     */
    public function notFound(): void
    {
        http_response_code(404);
        
        if ($this->isApi()) {
            $this->error('Not Found', 404);
            return;
        }
        
        $this->render('errors/404', [
            'title' => 'Page Not Found',
        ], null);
    }

    /**
     * 500 Server Error page
     * 
     * @return void
     */
    public function serverError(): void
    {
        http_response_code(500);
        
        if ($this->isApi()) {
            $this->error('Internal Server Error', 500);
            return;
        }
        
        $this->render('errors/500', [
            'title' => 'Server Error',
        ], null);
    }
}
