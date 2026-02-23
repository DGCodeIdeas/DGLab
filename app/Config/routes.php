<?php
/**
 * DGLab PWA - Routes Configuration
 * 
 * This file defines all application routes.
 * Routes are registered with the router instance.
 * 
 * @package DGLab\Config
 * @author DGLab Team
 * @version 1.0.0
 */

use DGLab\Core\Router;

/** @var Router $router */

// =============================================================================
// HOME ROUTES
// =============================================================================

// Home page
$router->get('/', 'HomeController@index', 'home');

// About page
$router->get('/about', 'HomeController@about', 'about');

// Documentation
$router->get('/docs', 'HomeController@docs', 'docs');

// =============================================================================
// TOOL ROUTES
// =============================================================================

// Tools listing page
$router->get('/tools', 'ToolController@index', 'tools.index');

// Tool category page
$router->get('/tools/category/{category}', 'ToolController@category', 'tools.category');

// Tool detail page
$router->get('/tool/{id}', 'ToolController@show', 'tools.show');

// Tool process page (POST)
$router->post('/tool/{id}/process', 'ToolController@process', 'tools.process');

// Tool progress (AJAX)
$router->get('/tool/{id}/progress/{jobId}', 'ToolController@progress', 'tools.progress');

// Tool download
$router->get('/tool/{id}/download/{filename}', 'ToolController@download', 'tools.download');

// =============================================================================
// UPLOAD ROUTES
// =============================================================================

// Initialize chunked upload
$router->post('/upload/init', 'UploadController@init', 'upload.init');

// Upload chunk
$router->post('/upload/chunk', 'UploadController@chunk', 'upload.chunk');

// Get upload progress
$router->get('/upload/progress/{uploadId}', 'UploadController@progress', 'upload.progress');

// Cancel upload
$router->delete('/upload/cancel/{uploadId}', 'UploadController@cancel', 'upload.cancel');

// =============================================================================
// API ROUTES
// =============================================================================

$router->group(['prefix' => 'api/v1'], function (Router $router) {
    
    // API status
    $router->get('/status', 'ApiController@status', 'api.status');
    
    // Tools API
    $router->get('/tools', 'ApiController@tools', 'api.tools');
    $router->get('/tools/{id}', 'ApiController@toolDetail', 'api.tool.detail');
    $router->get('/tools/{id}/config', 'ApiController@toolConfig', 'api.tool.config');
    
    // Process API
    $router->post('/process/{toolId}', 'ApiController@process', 'api.process');
    $router->get('/process/{jobId}/status', 'ApiController@jobStatus', 'api.job.status');
    
    // Validate API
    $router->post('/validate/{toolId}', 'ApiController@validate', 'api.validate');
    
    // Novel to Manga API Key Management
    $router->post('/novel-to-manga/apikey', 'ApiController@saveApiKey', 'api.novelmanga.key.save');
    $router->delete('/novel-to-manga/apikey', 'ApiController@deleteApiKey', 'api.novelmanga.key.delete');
    $router->get('/novel-to-manga/apikey/{provider}', 'ApiController@checkApiKey', 'api.novelmanga.key.check');
});

// =============================================================================
// ASSET ROUTES (for bundled assets)
// =============================================================================

$router->get('/assets/css/{file}', 'AssetController@css', 'assets.css');
$router->get('/assets/js/{file}', 'AssetController@js', 'assets.js');

// =============================================================================
// PWA ROUTES
// =============================================================================

// Manifest.json
$router->get('/manifest.json', 'PwaController@manifest', 'pwa.manifest');

// Service Worker
$router->get('/sw.js', 'PwaController@serviceWorker', 'pwa.sw');

// Offline page
$router->get('/offline', 'PwaController@offline', 'pwa.offline');

// =============================================================================
// ERROR ROUTES
// =============================================================================

// 404 Not Found
$router->get('/404', 'ErrorController@notFound', 'error.404');

// 500 Server Error
$router->get('/500', 'ErrorController@serverError', 'error.500');

// =============================================================================
// MAINTENANCE ROUTES
// =============================================================================

// Clear cache (admin only)
$router->post('/admin/clear-cache', 'AdminController@clearCache', 'admin.clear-cache');

// System info (admin only)
$router->get('/admin/system-info', 'AdminController@systemInfo', 'admin.system-info');
