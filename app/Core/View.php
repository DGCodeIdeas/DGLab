<?php
/**
 * DGLab PWA - View Class
 * 
 * The View class handles template rendering with support for:
 * - Template inheritance (layouts)
 * - Partial templates
 * - Escaped and unescaped output
 * - Helper functions
 * - Asset inclusion
 * 
 * @package DGLab\Core
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Core;

/**
 * View Class
 * 
 * Handles all view rendering operations with security features
 * and helper methods for common view tasks.
 */
class View
{
    /**
     * @var string $viewsPath Path to views directory
     */
    private string $viewsPath;
    
    /**
     * @var string $cachePath Path to cache directory
     */
    private string $cachePath;
    
    /**
     * @var array $sections Section content for layouts
     */
    private array $sections = [];
    
    /**
     * @var string|null $currentSection Currently open section
     */
    private ?string $currentSection = null;
    
    /**
     * @var array $helpers Registered view helpers
     */
    private array $helpers = [];
    
    /**
     * @var bool $cacheEnabled Whether view caching is enabled
     */
    private bool $cacheEnabled;

    /**
     * Constructor
     * 
     * @param string|null $viewsPath Custom views path
     * @param string|null $cachePath Custom cache path
     */
    public function __construct(?string $viewsPath = null, ?string $cachePath = null)
    {
        global $config;
        
        $this->viewsPath = $viewsPath ?? VIEWS_PATH;
        $this->cachePath = $cachePath ?? CACHE_PATH . '/views';
        $this->cacheEnabled = $config['view']['cache'] ?? false;
        
        // Create cache directory if needed
        if ($this->cacheEnabled && !is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
        
        // Register default helpers
        $this->registerDefaultHelpers();
    }

    // =============================================================================
    // RENDERING METHODS
    // =============================================================================

    /**
     * Render a view template
     * 
     * @param string $view View file path (relative to views directory)
     * @param array $data Data to pass to the view
     * @param bool $output Whether to output directly or return as string
     * @return string|null Rendered content if $output is false
     * @throws \Exception If view file not found
     */
    public function render(string $view, array $data = [], bool $output = true): ?string
    {
        // Build full view path
        $viewFile = $this->viewsPath . '/' . $view . '.php';
        
        // Check if view exists
        if (!file_exists($viewFile)) {
            throw new \Exception("View not found: {$view}");
        }
        
        // Check cache
        if ($this->cacheEnabled && !$this->isCacheStale($viewFile)) {
            $content = $this->getCachedView($viewFile);
            if ($output) {
                echo $content;
                return null;
            }
            return $content;
        }
        
        // Extract data to local scope
        extract($data);
        
        // Start output buffering
        ob_start();
        
        try {
            // Include view file
            include $viewFile;
            
            // Get rendered content
            $content = ob_get_clean();
            
            // Cache if enabled
            if ($this->cacheEnabled) {
                $this->cacheView($viewFile, $content);
            }
            
            // Output or return
            if ($output) {
                echo $content;
                return null;
            }
            
            return $content;
            
        } catch (\Exception $e) {
            // Clean buffer on error
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Render a view with layout
     * 
     * @param string $view View file path
     * @param string $layout Layout file path
     * @param array $data Data to pass to the view
     * @return void
     */
    public function renderWithLayout(string $view, string $layout, array $data = []): void
    {
        // Render the view content first
        $content = $this->render($view, $data, false);
        
        // Add content to data
        $data['content'] = $content;
        
        // Render layout
        $this->render("layouts/{$layout}", $data);
    }

    // =============================================================================
    // SECTION METHODS (for template inheritance)
    // =============================================================================

    /**
     * Start a section
     * 
     * @param string $name Section name
     * @return void
     */
    public function section(string $name): void
    {
        if ($this->currentSection !== null) {
            throw new \Exception("Cannot nest sections. Close '{$this->currentSection}' first.");
        }
        
        $this->currentSection = $name;
        ob_start();
    }

    /**
     * End a section
     * 
     * @return void
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new \Exception("No section to end.");
        }
        
        $this->sections[$this->currentSection] = ob_get_clean();
        $this->currentSection = null;
    }

    /**
     * Output a section
     * 
     * @param string $name Section name
     * @param string $default Default content if section not defined
     * @return void
     */
    public function yield(string $name, string $default = ''): void
    {
        echo $this->sections[$name] ?? $default;
    }

    /**
     * Check if section exists
     * 
     * @param string $name Section name
     * @return bool True if section exists
     */
    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    // =============================================================================
    // HELPER METHODS
    // =============================================================================

    /**
     * Register a view helper
     * 
     * @param string $name Helper name
     * @param callable $callback Helper function
     * @return self For method chaining
     */
    public function registerHelper(string $name, callable $callback): self
    {
        $this->helpers[$name] = $callback;
        return $this;
    }

    /**
     * Call a helper function
     * 
     * @param string $name Helper name
     * @param array $args Arguments to pass
     * @return mixed Helper result
     * @throws \Exception If helper not found
     */
    public function helper(string $name, ...$args)
    {
        if (!isset($this->helpers[$name])) {
            throw new \Exception("Helper not found: {$name}");
        }
        
        return call_user_func_array($this->helpers[$name], $args);
    }

    /**
     * Register default view helpers
     * 
     * @return void
     */
    private function registerDefaultHelpers(): void
    {
        // Asset URL helper
        $this->registerHelper('asset', function (string $path): string {
            $baseUrl = $GLOBALS['config']['app']['base_url'] ?? '';
            return rtrim($baseUrl, '/') . '/assets/' . ltrim($path, '/');
        });
        
        // Route URL helper
        $this->registerHelper('route', function (string $name, array $params = []): string {
            global $router;
            return $router->route($name, $params);
        });
        
        // URL helper
        $this->registerHelper('url', function (string $path = ''): string {
            $baseUrl = $GLOBALS['config']['app']['base_url'] ?? '';
            return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        });
        
        // Format date helper
        $this->registerHelper('date', function ($date, string $format = 'Y-m-d H:i'): string {
            if (is_string($date)) {
                $date = strtotime($date);
            }
            return date($format, $date);
        });
        
        // Format number helper
        $this->registerHelper('number', function ($number, int $decimals = 0): string {
            return number_format($number, $decimals);
        });
        
        // Truncate text helper
        $this->registerHelper('truncate', function (string $text, int $length = 100, string $suffix = '...'): string {
            if (strlen($text) <= $length) {
                return $text;
            }
            return substr($text, 0, $length) . $suffix;
        });
        
        // Slug helper
        $this->registerHelper('slug', function (string $text): string {
            return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
        });
    }

    // =============================================================================
    // ESCAPING METHODS
    // =============================================================================

    /**
     * Escape HTML entities
     * 
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Output escaped text (shortcut)
     * 
     * @param string $text Text to escape and output
     * @return void
     */
    public static function out(string $text): void
    {
        echo self::e($text);
    }

    // =============================================================================
    // ASSET METHODS
    // =============================================================================

    /**
     * Include CSS file
     * 
     * @param string $path CSS file path
     * @param array $attributes Additional attributes
     * @return string HTML link tag
     */
    public function css(string $path, array $attributes = []): string
    {
        $attrs = $this->buildAttributes(array_merge([
            'rel' => 'stylesheet',
            'href' => $this->helper('asset', $path),
        ], $attributes));
        
        return "<link{$attrs}>\n";
    }

    /**
     * Include JavaScript file
     * 
     * @param string $path JS file path
     * @param array $attributes Additional attributes
     * @param bool $defer Add defer attribute
     * @return string HTML script tag
     */
    public function js(string $path, array $attributes = [], bool $defer = true): string
    {
        $attrs = [
            'src' => $this->helper('asset', $path),
        ];
        
        if ($defer) {
            $attrs['defer'] = true;
        }
        
        $attrs = $this->buildAttributes(array_merge($attrs, $attributes));
        
        return "<script{$attrs}></script>\n";
    }

    /**
     * Build HTML attributes string
     * 
     * @param array $attributes Attributes array
     * @return string Attributes string
     */
    private function buildAttributes(array $attributes): string
    {
        $attrs = [];
        
        foreach ($attributes as $name => $value) {
            if ($value === true) {
                $attrs[] = $name;
            } elseif ($value !== false && $value !== null) {
                $attrs[] = $name . '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
            }
        }
        
        return empty($attrs) ? '' : ' ' . implode(' ', $attrs);
    }

    // =============================================================================
    // CACHE METHODS
    // =============================================================================

    /**
     * Check if cached view is stale
     * 
     * @param string $viewFile Original view file
     * @return bool True if cache is stale or doesn't exist
     */
    private function isCacheStale(string $viewFile): bool
    {
        $cacheFile = $this->getCacheFile($viewFile);
        
        if (!file_exists($cacheFile)) {
            return true;
        }
        
        return filemtime($viewFile) > filemtime($cacheFile);
    }

    /**
     * Get cached view content
     * 
     * @param string $viewFile Original view file
     * @return string Cached content
     */
    private function getCachedView(string $viewFile): string
    {
        $cacheFile = $this->getCacheFile($viewFile);
        return file_get_contents($cacheFile);
    }

    /**
     * Cache view content
     * 
     * @param string $viewFile Original view file
     * @param string $content Content to cache
     * @return void
     */
    private function cacheView(string $viewFile, string $content): void
    {
        $cacheFile = $this->getCacheFile($viewFile);
        
        // Ensure cache directory exists
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        file_put_contents($cacheFile, $content);
    }

    /**
     * Get cache file path for a view
     * 
     * @param string $viewFile Original view file
     * @return string Cache file path
     */
    private function getCacheFile(string $viewFile): string
    {
        $relativePath = str_replace($this->viewsPath, '', $viewFile);
        $cacheKey = md5($relativePath);
        return $this->cachePath . '/' . $cacheKey . '.cache';
    }

    /**
     * Clear view cache
     * 
     * @return void
     */
    public function clearCache(): void
    {
        if (is_dir($this->cachePath)) {
            $files = glob($this->cachePath . '/*.cache');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
}
