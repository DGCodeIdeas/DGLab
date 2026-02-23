<?php
/**
 * DGLab PWA - Asset Controller
 * 
 * Handles asset serving and compilation.
 * 
 * @package DGLab\Controllers
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Controllers;

use DGLab\Core\Controller;
use DGLab\Core\AssetBundler;

/**
 * AssetController Class
 * 
 * Controller for serving compiled assets.
 */
class AssetController extends Controller
{
    /**
     * Serve CSS file
     * 
     * @param string $file Filename
     * @return void
     */
    public function css(string $file): void
    {
        $cachePath = CACHE_PATH . '/assets/' . $file;
        
        if (!file_exists($cachePath)) {
            http_response_code(404);
            echo '/* File not found */';
            return;
        }
        
        header('Content-Type: text/css; charset=utf-8');
        header('Cache-Control: public, max-age=31536000');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        readfile($cachePath);
    }

    /**
     * Serve JS file
     * 
     * @param string $file Filename
     * @return void
     */
    public function js(string $file): void
    {
        $cachePath = CACHE_PATH . '/assets/' . $file;
        
        if (!file_exists($cachePath)) {
            http_response_code(404);
            echo '// File not found';
            return;
        }
        
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: public, max-age=31536000');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        readfile($cachePath);
    }
}
