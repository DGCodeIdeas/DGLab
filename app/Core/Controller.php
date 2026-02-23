<?php
/**
 * DGLab PWA - Base Controller Class
 * 
 * The Controller class serves as the base class for all application controllers.
 * It provides common functionality for:
 * - View rendering
 * - JSON responses for API endpoints
 * - Input validation and sanitization
 * - CSRF protection
 * - Flash messages
 * - Redirects
 * 
 * @package DGLab\Core
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Core;

/**
 * Base Controller Class
 * 
 * All application controllers should extend this class to inherit
 * common functionality and follow the MVC pattern.
 */
abstract class Controller
{
    /**
     * @var View $view View instance for rendering templates
     */
    protected View $view;
    
    /**
     * @var array $data Data to be passed to views
     */
    protected array $data = [];
    
    /**
     * @var array $config Application configuration
     */
    protected array $config = [];
    
    /**
     * @var Database|null $db Database instance
     */
    protected ?Database $db = null;

    /**
     * Constructor
     * 
     * Initializes the controller with common dependencies
     */
    public function __construct()
    {
        // Load configuration
        global $config;
        $this->config = $config ?? [];
        
        // Initialize view
        $this->view = new View();
        
        // Initialize database if configured
        if ($this->config['database']['enabled'] ?? false) {
            $this->db = Database::getInstance();
        }
        
        // Set default view data
        $this->data['app_name'] = APP_NAME;
        $this->data['app_version'] = APP_VERSION;
        $this->data['base_url'] = $this->config['app']['base_url'] ?? '';
        $this->data['csrf_token'] = $this->generateCsrfToken();
    }

    // =============================================================================
    // VIEW RENDERING METHODS
    // =============================================================================

    /**
     * Render a view template
     * 
     * @param string $view View file path (relative to views directory)
     * @param array $data Data to pass to the view
     * @param string|null $layout Layout file to use (null for no layout)
     * @return void
     */
    protected function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        // Merge data
        $data = array_merge($this->data, $data);
        
        // Render view
        $content = $this->view->render($view, $data, false);
        
        // Wrap in layout if specified
        if ($layout !== null) {
            $data['content'] = $content;
            $this->view->render("layouts/{$layout}", $data);
        } else {
            echo $content;
        }
    }

    /**
     * Render a partial view (no layout)
     * 
     * @param string $view View file path
     * @param array $data Data to pass to the view
     * @return string Rendered content
     */
    protected function partial(string $view, array $data = []): string
    {
        return $this->view->render($view, array_merge($this->data, $data), false);
    }

    // =============================================================================
    // API RESPONSE METHODS
    // =============================================================================

    /**
     * Send JSON response
     * 
     * @param mixed $data Data to encode as JSON
     * @param int $status HTTP status code
     * @param array $headers Additional headers
     * @return void
     */
    protected function json($data, int $status = 200, array $headers = []): void
    {
        // Set content type
        header('Content-Type: application/json; charset=utf-8');
        
        // Set status code
        http_response_code($status);
        
        // Set additional headers
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
        
        // Output JSON
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send success JSON response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $status HTTP status code
     * @return void
     */
    protected function success($data = null, string $message = 'Success', int $status = 200): void
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        $this->json($response, $status);
    }

    /**
     * Send error JSON response
     * 
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param mixed $errors Additional error details
     * @return void
     */
    protected function error(string $message = 'Error', int $status = 400, $errors = null): void
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        $this->json($response, $status);
    }

    // =============================================================================
    // INPUT HANDLING METHODS
    // =============================================================================

    /**
     * Get input value from GET, POST, or JSON body
     * 
     * @param string $key Input key
     * @param mixed $default Default value if not found
     * @return mixed Input value or default
     */
    protected function input(string $key, $default = null)
    {
        // Check POST first
        if (isset($_POST[$key])) {
            return $this->sanitize($_POST[$key]);
        }
        
        // Check GET
        if (isset($_GET[$key])) {
            return $this->sanitize($_GET[$key]);
        }
        
        // Check JSON body
        $json = $this->getJsonInput();
        if (isset($json[$key])) {
            return $json[$key];
        }
        
        return $default;
    }

    /**
     * Get all input data
     * 
     * @return array All input data
     */
    protected function all(): array
    {
        $input = array_merge($_GET, $_POST);
        
        // Add JSON body if present
        $json = $this->getJsonInput();
        if (!empty($json)) {
            $input = array_merge($input, $json);
        }
        
        return $this->sanitize($input);
    }

    /**
     * Get JSON input from request body
     * 
     * @return array Decoded JSON data
     */
    protected function getJsonInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $body = file_get_contents('php://input');
            $json = json_decode($body, true);
            return is_array($json) ? $json : [];
        }
        
        return [];
    }

    /**
     * Sanitize input data
     * 
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    protected function sanitize($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        
        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace("\0", '', $data);
            // Trim whitespace
            $data = trim($data);
            // Convert special characters to HTML entities
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        
        return $data;
    }

    /**
     * Validate input data against rules
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return array Validation errors (empty if valid)
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $ruleSet) {
            $rulesArray = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            
            foreach ($rulesArray as $rule) {
                $error = $this->validateRule($field, $data[$field] ?? null, $rule);
                if ($error !== null) {
                    $errors[$field][] = $error;
                }
            }
        }
        
        return $errors;
    }

    /**
     * Validate a single rule
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule
     * @return string|null Error message or null if valid
     */
    private function validateRule(string $field, $value, string $rule): ?string
    {
        // Parse rule with parameters
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $param = $parts[1] ?? null;
        
        switch ($ruleName) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    return "The {$field} field is required.";
                }
                break;
                
            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "The {$field} must be a valid email address.";
                }
                break;
                
            case 'min':
                if ($value !== null && strlen((string)$value) < (int)$param) {
                    return "The {$field} must be at least {$param} characters.";
                }
                break;
                
            case 'max':
                if ($value !== null && strlen((string)$value) > (int)$param) {
                    return "The {$field} must not exceed {$param} characters.";
                }
                break;
                
            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    return "The {$field} must be numeric.";
                }
                break;
                
            case 'integer':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
                    return "The {$field} must be an integer.";
                }
                break;
                
            case 'url':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return "The {$field} must be a valid URL.";
                }
                break;
                
            case 'in':
                $allowed = explode(',', $param);
                if ($value !== null && !in_array($value, $allowed, true)) {
                    return "The {$field} must be one of: " . implode(', ', $allowed) . ".";
                }
                break;
        }
        
        return null;
    }

    // =============================================================================
    // CSRF PROTECTION METHODS
    // =============================================================================

    /**
     * Generate CSRF token
     * 
     * @return string CSRF token
     */
    protected function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     * 
     * @param string|null $token Token to validate (uses input if null)
     * @return bool True if valid
     */
    protected function validateCsrfToken(?string $token = null): bool
    {
        $token = $token ?? $this->input('csrf_token');
        
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Require valid CSRF token (throws error if invalid)
     * 
     * @return void
     */
    protected function requireCsrfToken(): void
    {
        if (!$this->validateCsrfToken()) {
            $this->error('Invalid CSRF token', 403);
        }
    }

    // =============================================================================
    // FLASH MESSAGE METHODS
    // =============================================================================

    /**
     * Set flash message
     * 
     * @param string $type Message type (success, error, warning, info)
     * @param string $message Message text
     * @return void
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash_messages'][$type][] = $message;
    }

    /**
     * Get and clear flash messages
     * 
     * @return array Flash messages
     */
    protected function getFlashMessages(): array
    {
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return $messages;
    }

    // =============================================================================
    // REDIRECT METHODS
    // =============================================================================

    /**
     * Redirect to URL
     * 
     * @param string $url URL to redirect to
     * @param int $status HTTP status code
     * @return void
     */
    protected function redirect(string $url, int $status = 302): void
    {
        header("Location: {$url}", true, $status);
        exit;
    }

    /**
     * Redirect back to previous page
     * 
     * @param int $status HTTP status code
     * @return void
     */
    protected function back(int $status = 302): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer, $status);
    }

    /**
     * Redirect to named route
     * 
     * @param string $name Route name
     * @param array $params Route parameters
     * @param int $status HTTP status code
     * @return void
     */
    protected function route(string $name, array $params = [], int $status = 302): void
    {
        global $router;
        $url = $router->route($name, $params);
        $this->redirect($url, $status);
    }

    // =============================================================================
    // UTILITY METHODS
    // =============================================================================

    /**
     * Check if request is AJAX
     * 
     * @return bool True if AJAX request
     */
    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Check if request is API call
     * 
     * @return bool True if API request
     */
    protected function isApi(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        
        return strpos($contentType, 'application/json') !== false ||
               strpos($accept, 'application/json') !== false ||
               isset($_GET['__type__']) && $_GET['__type__'] === 'api';
    }

    /**
     * Get request method
     * 
     * @return string HTTP method
     */
    protected function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Check if request method matches
     * 
     * @param string $method Method to check
     * @return bool True if matches
     */
    protected function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }
}
