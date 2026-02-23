<?php
/**
 * DGLab PWA - Home Controller
 * 
 * Handles home page and general pages.
 * 
 * @package DGLab\Controllers
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Controllers;

use DGLab\Core\Controller;
use DGLab\Tools\ToolRegistry;

/**
 * HomeController Class
 * 
 * Controller for home and general pages.
 */
class HomeController extends Controller
{
    /**
     * Home page
     * 
     * @return void
     */
    public function index(): void
    {
        // Get featured tools
        $registry = ToolRegistry::getInstance();
        $tools = $registry->getAll();
        
        // Get categories
        $categories = $registry->getCategories();
        
        $this->render('home/index', [
            'title'      => 'Welcome to ' . APP_NAME,
            'tools'      => $tools,
            'categories' => $categories,
            'featured'   => array_slice($tools, 0, 6, true),
        ]);
    }

    /**
     * About page
     * 
     * @return void
     */
    public function about(): void
    {
        $this->render('home/about', [
            'title'       => 'About ' . APP_NAME,
            'description' => 'Learn more about DGLab PWA and its features.',
        ]);
    }

    /**
     * Documentation page
     * 
     * @return void
     */
    public function docs(): void
    {
        $this->render('home/docs', [
            'title' => 'Documentation',
        ]);
    }
}
