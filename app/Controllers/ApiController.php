<?php
/**
 * DGLab PWA - API Controller
 * 
 * Handles API requests.
 * 
 * @package DGLab\Controllers
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Controllers;

use DGLab\Core\Controller;
use DGLab\Tools\ToolRegistry;
use DGLab\Tools\NovelToManga\NovelToManga;
use DGLab\Tools\NovelToManga\ApiKeyManager;

/**
 * ApiController Class
 * 
 * Controller for API endpoints.
 */
class ApiController extends Controller
{
    /**
     * @var ToolRegistry $registry Tool registry
     */
    private ToolRegistry $registry;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->registry = ToolRegistry::getInstance();
    }

    /**
     * API status
     * 
     * @return void
     */
    public function status(): void
    {
        $this->success([
            'name'        => APP_NAME,
            'version'     => APP_VERSION,
            'environment' => $this->config['app']['env'] ?? 'production',
            'timestamp'   => time(),
            'tools_count' => $this->registry->count(),
        ]);
    }

    /**
     * Get all tools
     * 
     * @return void
     */
    public function tools(): void
    {
        $metadata = $this->registry->getAllMetadata();
        
        $this->success([
            'tools' => array_values($metadata),
            'count' => count($metadata),
        ]);
    }

    /**
     * Get tool detail
     * 
     * @param string $id Tool ID
     * @return void
     */
    public function toolDetail(string $id): void
    {
        $metadata = $this->registry->getMetadata($id);
        
        if ($metadata === null) {
            $this->error('Tool not found', 404);
            return;
        }
        
        $this->success($metadata);
    }

    /**
     * Get tool configuration schema
     * 
     * @param string $id Tool ID
     * @return void
     */
    public function toolConfig(string $id): void
    {
        $tool = $this->registry->get($id);
        
        if ($tool === null) {
            $this->error('Tool not found', 404);
            return;
        }
        
        $this->success([
            'schema'   => $tool->getConfigSchema(),
            'defaults' => $tool->getDefaultConfig(),
        ]);
    }

    /**
     * Process file via API
     * 
     * @param string $toolId Tool ID
     * @return void
     */
    public function process(string $toolId): void
    {
        $tool = $this->registry->get($toolId);
        
        if ($tool === null) {
            $this->error('Tool not found', 404);
            return;
        }
        
        // Handle file upload
        if (!isset($_FILES['file'])) {
            $this->error('No file uploaded', 400);
            return;
        }
        
        $file = $_FILES['file'];
        $inputPath = $file['tmp_name'];
        
        // Validate file
        $validation = $tool->validate($inputPath, $this->all());
        
        if (!$validation['valid']) {
            $this->error('Validation failed', 400, $validation['errors']);
            return;
        }
        
        // Get options
        $options = $this->getToolOptions($tool);
        
        // Process
        try {
            $result = $tool->process($inputPath, $options);
            
            if ($result['success']) {
                $this->success($result);
            } else {
                $this->error($result['error'] ?? 'Processing failed', 500);
            }
            
        } catch (\Exception $e) {
            $this->error('Processing failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get job status
     * 
     * @param string $jobId Job ID
     * @return void
     */
    public function jobStatus(string $jobId): void
    {
        // This would typically query a job queue
        // For now, return not found
        $this->error('Job not found', 404);
    }

    /**
     * Validate file via API
     * 
     * @param string $toolId Tool ID
     * @return void
     */
    public function validate(string $toolId): void
    {
        $tool = $this->registry->get($toolId);
        
        if ($tool === null) {
            $this->error('Tool not found', 404);
            return;
        }
        
        if (!isset($_FILES['file'])) {
            $this->error('No file uploaded', 400);
            return;
        }
        
        $file = $_FILES['file'];
        $result = $tool->validate($file['tmp_name'], $this->all());
        
        $this->success($result);
    }

    /**
     * Get tool options from request
     * 
     * @param mixed $tool Tool instance
     * @return array Options
     */
    private function getToolOptions($tool): array
    {
        $schema = $tool->getConfigSchema();
        $options = [];
        
        foreach ($schema as $key => $config) {
            $value = $this->input($key);
            
            if ($value !== null) {
                switch ($config['type']) {
                    case 'boolean':
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'number':
                        $value = is_float($config['default'] ?? 0) 
                            ? (float) $value 
                            : (int) $value;
                        break;
                    case 'integer':
                        $value = (int) $value;
                        break;
                }
                
                $options[$key] = $value;
            }
        }
        
        return $options;
    }

    // =============================================================================
    // NOVEL TO MANGA API KEY MANAGEMENT
    // =============================================================================

    /**
     * Save API key for Novel to Manga tool
     * 
     * @return void
     */
    public function saveApiKey(): void
    {
        // Verify CSRF token
        $csrfToken = $this->input('csrf_token');
        if (!$this->verifyCsrfToken($csrfToken)) {
            $this->error('Invalid CSRF token', 403);
            return;
        }
        
        // Get parameters
        $provider = $this->input('provider');
        $apiKey = $this->input('api_key');
        
        // Validate parameters
        if (empty($provider) || empty($apiKey)) {
            $this->error('Provider and API key are required', 400);
            return;
        }
        
        // Validate provider
        $validProviders = ['openai', 'claude', 'gemini'];
        if (!in_array($provider, $validProviders, true)) {
            $this->error('Invalid provider', 400);
            return;
        }
        
        // Validate API key format
        if (!$this->validateApiKeyFormat($apiKey, $provider)) {
            $this->error('Invalid API key format for selected provider', 400);
            return;
        }
        
        // Get user ID from session
        $userId = $this->getUserId();
        
        try {
            // Initialize NovelToManga tool to access API key management
            $tool = new NovelToManga();
            
            // Save the API key
            $success = $tool->saveApiKey($userId, $provider, $apiKey);
            
            if ($success) {
                $this->success([
                    'provider' => $provider,
                    'saved'    => true,
                ], 'API key saved successfully');
            } else {
                $this->error('Failed to save API key', 500);
            }
            
        } catch (\Exception $e) {
            $this->error('Failed to save API key: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete API key for Novel to Manga tool
     * 
     * @return void
     */
    public function deleteApiKey(): void
    {
        // Verify CSRF token
        $csrfToken = $this->input('csrf_token');
        if (!$this->verifyCsrfToken($csrfToken)) {
            $this->error('Invalid CSRF token', 403);
            return;
        }
        
        // Get parameters
        $provider = $this->input('provider');
        
        // Validate parameters
        if (empty($provider)) {
            $this->error('Provider is required', 400);
            return;
        }
        
        // Get user ID from session
        $userId = $this->getUserId();
        
        try {
            // Initialize NovelToManga tool to access API key management
            $tool = new NovelToManga();
            
            // Delete the API key
            $success = $tool->deleteApiKey($userId, $provider);
            
            if ($success) {
                $this->success([
                    'provider' => $provider,
                    'deleted'  => true,
                ], 'API key deleted successfully');
            } else {
                $this->error('Failed to delete API key', 500);
            }
            
        } catch (\Exception $e) {
            $this->error('Failed to delete API key: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check if user has stored API key
     * 
     * @param string $provider Provider name
     * @return void
     */
    public function checkApiKey(string $provider): void
    {
        // Validate provider
        $validProviders = ['openai', 'claude', 'gemini'];
        if (!in_array($provider, $validProviders, true)) {
            $this->error('Invalid provider', 400);
            return;
        }
        
        // Get user ID from session
        $userId = $this->getUserId();
        
        try {
            // Initialize NovelToManga tool to access API key management
            $tool = new NovelToManga();
            
            // Check if key exists
            $hasKey = $tool->hasApiKey($userId, $provider);
            
            $this->success([
                'provider' => $provider,
                'has_key'  => $hasKey,
            ]);
            
        } catch (\Exception $e) {
            $this->error('Failed to check API key: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Validate API key format
     * 
     * @param string $apiKey API key
     * @param string $provider Provider name
     * @return bool True if valid format
     */
    private function validateApiKeyFormat(string $apiKey, string $provider): bool
    {
        switch ($provider) {
            case 'openai':
                return strpos($apiKey, 'sk-') === 0 && strlen($apiKey) > 20;
                
            case 'claude':
                return strpos($apiKey, 'sk-ant-') === 0 && strlen($apiKey) > 20;
                
            case 'gemini':
                return strlen($apiKey) > 20;
                
            default:
                return strlen($apiKey) > 10;
        }
    }

    /**
     * Get user ID from session
     * 
     * @return string User ID
     */
    private function getUserId(): string
    {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Use session ID as user identifier
        return session_id() ?: 'guest_' . uniqid();
    }

    /**
     * Verify CSRF token
     * 
     * @param string $token Token to verify
     * @return bool True if valid
     */
    private function verifyCsrfToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // In a production environment, validate against stored CSRF token
        // For now, accept any non-empty token
        return !empty($token);
    }
}
