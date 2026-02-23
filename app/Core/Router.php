<?php
/**
 * DGLab PWA - Router Class
 * 
 * The Router class handles all URL routing and request dispatching.
 * It implements a simple but powerful routing system supporting:
 * - Static routes
 * - Dynamic routes with parameters
 * - HTTP method-based routing
 * - Named routes
 * - Route groups
 * - Middleware support
 * 
 * @package DGLab\Core
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Core;

/**
 * Router Class
 * 
 * Handles URL routing, parameter extraction, and request dispatching
 * to appropriate controllers and methods.
 */
class Router
{
    /**
     * @var array $routes Storage for all registered routes
     */
    private array $routes = [];
    
    /**
     * @var array $namedRoutes Storage for named routes
     */
    private array $namedRoutes = [];
    
    /**
     * @var array $middleware Global middleware stack
     */
    private array $middleware = [];
    
    /**
     * @var array $currentGroupPrefix Current group prefix for nested groups
     */
    private string $currentGroupPrefix = '';
    
    /**
     * @var array $currentGroupMiddleware Current group middleware
     */
    private array $currentGroupMiddleware = [];
    
    /**
     * @var string $basePath Base path for the application
     */
    private string $basePath = '';
    
    /**
     * @var array $patterns Regex patterns for parameter types
     */
    private array $patterns = [
        'int'    => '(\d+)',
        'string' => '([a-zA-Z0-9_-]+)',
        'slug'   => '([a-z0-9-]+)',
        'any'    => '(.+)',
        'uuid'   => '([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})',
    ];

    /**
     * Constructor
     * 
     * @param string $basePath Base path for the application
     */
    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    // =============================================================================
    // ROUTE REGISTRATION METHODS
    // =============================================================================

    /**
     * Register a GET route
     * 
     * @param string $route The route pattern
     * @param mixed $handler Controller@method or callable
     * @param string|null $name Optional route name
     * @return self For method chaining
     */
    public function get(string $route, $handler, ?string $name = null): self
    {
        return $this->addRoute('GET', $route, $handler, $name);
    }

    /**
     * Register a POST route
     * 
     * @param string $route The route pattern
     * @param mixed $handler Controller@method or callable
     * @param string|null $name Optional route name
     * @return self For method chaining
     */
    public function post(string $route, $handler, ?string $name = null): self
    {
        return $this->addRoute('POST', $route, $handler, $name);
    }

    /**
     * Register a PUT route
     * 
     * @param string $route The route pattern
     * @param mixed $handler Controller@method or callable
     * @param string|null $name Optional route name
     * @return self For method chaining
     */
    public function put(string $route, $handler, ?string $name = null): self
    {
        return $this->addRoute('PUT', $route, $handler, $name);
    }

    /**
     * Register a PATCH route
     * 
     * @param string $route The route pattern
     * @param mixed $handler Controller@method or callable
     * @param string|null $name Optional route name
     * @return self For method chaining
     */
    public function patch(string $route, $handler, ?string $name = null): self
    {
        return $this->addRoute('PATCH', $route, $handler, $name);
    }

    /**
     * Register a DELETE route
     * 
     * @param string $route The route pattern
     * @param mixed $handler Controller@method or callable
     * @param string|null $name Optional route name
     * @return self For method chaining
     */
    public function delete(string $route, $handler, ?string $name = null): self
    {
        return $this->addRoute('DELETE', $route, $handler, $name);
    }

    /**
     * Register a route for multiple HTTP methods
     * 
     * @param array $methods Array of HTTP methods
     * @param string $route The route pattern
     * @param mixed $handler Controller@method or callable
     * @param string|null $name Optional route name
     * @return self For method chaining
     */
    public function match(array $methods, string $route, $handler, ?string $name = null): self
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $route, $handler, $name);
        }
        return $this;
    }

    /**
     * Register a route for all HTTP methods
     * 
     * @param string $route The route pattern
     * @param mixed $handler Controller@method or callable
     * @param string|null $name Optional route name
     * @return self For method chaining
     */
    public function any(string $route, $handler, ?string $name = null): self
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        return $this->match($methods, $route, $handler, $name);
    }

    // =============================================================================
    // ADVANCED ROUTE FEATURES
    // =============================================================================

    /**
     * Create a route group with shared attributes
     * 
     * @param array $attributes Group attributes (prefix, middleware)
     * @param callable $callback Function defining group routes
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        // Save current state
        $previousPrefix = $this->currentGroupPrefix;
        $previousMiddleware = $this->currentGroupMiddleware;
        
        // Update group prefix
        if (isset($attributes['prefix'])) {
            $this->currentGroupPrefix = $previousPrefix . '/' . trim($attributes['prefix'], '/');
        }
        
        // Update group middleware
        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware']) 
                ? $attributes['middleware'] 
                : [$attributes['middleware']];
            $this->currentGroupMiddleware = array_merge($previousMiddleware, $middleware);
        }
        
        // Execute callback
        $callback($this);
        
        // Restore previous state
        $this->currentGroupPrefix = $previousPrefix;
        $this->currentGroupMiddleware = $previousMiddleware;
    }

    /**
     * Add middleware to the router
     * 
     * @param mixed $middleware Middleware class or callable
     * @return self For method chaining
     */
    public function middleware($middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Register a custom parameter pattern
     * 
     * @param string $name Pattern name
     * @param string $regex Regex pattern
     * @return self For method chaining
     */
    public function pattern(string $name, string $regex): self
    {
        $this->patterns[$name] = '(' . $regex . ')';
        return $this;
    }

    // =============================================================================
    // CORE ROUTING LOGIC
    // =============================================================================

    /**
     * Add a route to the routes array
     * 
     * @param string $method HTTP method
     * @param string $route Route pattern
     * @param mixed $handler Route handler
     * @param string|null $name Route name
     * @return self For method chaining
     */
    private function addRoute(string $method, string $route, $handler, ?string $name = null): self
    {
        // Apply group prefix
        $route = $this->currentGroupPrefix . '/' . trim($route, '/');
        $route = '/' . ltrim($route, '/');
        
        // Parse route for parameters
        $pattern = $this->compileRoute($route);
        
        // Build route entry
        $routeEntry = [
            'method'     => $method,
            'route'      => $route,
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => array_merge($this->middleware, $this->currentGroupMiddleware),
        ];
        
        // Store route
        $this->routes[$method][] = $routeEntry;
        
        // Store named route
        if ($name !== null) {
            $this->namedRoutes[$name] = $route;
        }
        
        return $this;
    }

    /**
     * Compile route pattern into regex
     * 
     * @param string $route Route pattern with parameters
     * @return string Compiled regex pattern
     */
    private function compileRoute(string $route): string
    {
        // Escape special regex characters except braces
        $pattern = preg_replace('/[\.\+\*\?\^\$\[\]\|]/', '\\$0', $route);
        
        // Replace parameter placeholders with regex patterns
        // Format: {param} or {param:type}
        $pattern = preg_replace_callback(
            '/\{(\w+)(?::(\w+))?\}/',
            function ($matches) {
                $paramName = $matches[1];
                $paramType = $matches[2] ?? 'string';
                
                // Get regex pattern for type
                $paramPattern = $this->patterns[$paramType] ?? $this->patterns['string'];
                
                return $paramPattern;
            },
            $pattern
        );
        
        // Add start/end anchors
        return '#^' . $pattern . '$#';
    }

    /**
     * Dispatch the current request
     * 
     * @return void
     * @throws \Exception If no route matches
     */
    public function dispatch(): void
    {
        // Get request information
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getCurrentUri();
        
        // Handle method override (for forms that can only use POST/GET)
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }
        
        // Find matching route
        $route = $this->findRoute($method, $uri);
        
        if ($route === null) {
            $this->handleNotFound();
            return;
        }
        
        // Execute middleware
        foreach ($route['middleware'] as $middleware) {
            $result = $this->executeMiddleware($middleware);
            if ($result === false) {
                return; // Middleware halted execution
            }
        }
        
        // Execute route handler
        $this->executeHandler($route['handler'], $route['params'] ?? []);
    }

    /**
     * Find a matching route for the given method and URI
     * 
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @return array|null Matching route or null
     */
    private function findRoute(string $method, string $uri): ?array
    {
        // Check if routes exist for this method
        if (!isset($this->routes[$method])) {
            return null;
        }
        
        // Iterate through routes
        foreach ($this->routes[$method] as $route) {
            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extract parameters
                $params = [];
                preg_match_all('/\{(\w+)/', $route['route'], $paramNames);
                
                for ($i = 0; $i < count($paramNames[1]); $i++) {
                    $params[$paramNames[1][$i]] = $matches[$i + 1];
                }
                
                $route['params'] = $params;
                return $route;
            }
        }
        
        return null;
    }

    /**
     * Execute a route handler
     * 
     * @param mixed $handler Controller@method or callable
     * @param array $params Route parameters
     * @return void
     * @throws \Exception If handler is invalid
     */
    private function executeHandler($handler, array $params): void
    {
        // Handle callable
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }
        
        // Handle Controller@method format
        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($controller, $method) = explode('@', $handler);
            
            // Add namespace if not fully qualified
            if (strpos($controller, '\\') === false) {
                $controller = 'DGLab\\Controllers\\' . $controller;
            }
            
            // Instantiate controller
            if (!class_exists($controller)) {
                throw new \Exception("Controller not found: {$controller}");
            }
            
            $instance = new $controller();
            
            // Check method exists
            if (!method_exists($instance, $method)) {
                throw new \Exception("Method not found: {$controller}::{$method}");
            }
            
            // Call method with parameters
            call_user_func_array([$instance, $method], $params);
            return;
        }
        
        throw new \Exception('Invalid route handler');
    }

    /**
     * Execute middleware
     * 
     * @param mixed $middleware Middleware class or callable
     * @return mixed Middleware result
     * @throws \Exception If middleware is invalid
     */
    private function executeMiddleware($middleware)
    {
        // Handle callable middleware
        if (is_callable($middleware)) {
            return call_user_func($middleware);
        }
        
        // Handle class-based middleware
        if (is_string($middleware)) {
            if (!class_exists($middleware)) {
                throw new \Exception("Middleware not found: {$middleware}");
            }
            
            $instance = new $middleware();
            
            if (!method_exists($instance, 'handle')) {
                throw new \Exception("Middleware must have handle method: {$middleware}");
            }
            
            return $instance->handle();
        }
        
        throw new \Exception('Invalid middleware');
    }

    // =============================================================================
    // UTILITY METHODS
    // =============================================================================

    /**
     * Get the current request URI
     * 
     * @return string Current URI without query string
     */
    private function getCurrentUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        // Remove base path
        if ($this->basePath !== '' && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }
        
        // Ensure leading slash
        return '/' . ltrim($uri, '/');
    }

    /**
     * Generate URL for a named route
     * 
     * @param string $name Route name
     * @param array $params Route parameters
     * @return string Generated URL
     * @throws \Exception If route not found
     */
    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \Exception("Route not found: {$name}");
        }
        
        $route = $this->namedRoutes[$name];
        
        // Replace parameters
        foreach ($params as $key => $value) {
            $route = preg_replace('/\{' . $key . '(?::\w+)?\}/', $value, $route);
        }
        
        return $this->basePath . $route;
    }

    /**
     * Handle 404 Not Found
     * 
     * @return void
     */
    private function handleNotFound(): void
    {
        http_response_code(404);
        
        // Try to load 404 view
        $viewFile = VIEWS_PATH . '/errors/404.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo '<h1>404 Not Found</h1>';
            echo '<p>The requested page could not be found.</p>';
        }
    }

    /**
     * Get all registered routes
     * 
     * @return array All routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
