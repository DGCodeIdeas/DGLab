<?php
/**
 * DGLab Base Service
 * 
 * Abstract base class for all services providing common functionality.
 * 
 * @package DGLab\Services
 */

namespace DGLab\Services;

use DGLab\Core\ValidationException;
use DGLab\Services\Contracts\ServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseService
 * 
 * Provides common service functionality:
 * - Validation helpers
 * - Progress callback wrapper
 * - Temporary file management
 * - Logging integration
 * - Cleanup methods
 */
abstract class BaseService implements ServiceInterface
{
    /**
     * Service configuration
     */
    protected array $config = [];
    
    /**
     * Logger instance
     */
    protected ?LoggerInterface $logger = null;
    
    /**
     * Temporary files for cleanup
     */
    private array $tempFiles = [];
    
    /**
     * Temporary directories for cleanup
     */
    private array $tempDirs = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Load service configuration
     */
    protected function loadConfig(): void
    {
        $configPath = \DGLab\Core\Application::getInstance()->getBasePath() . '/config/services.php';
        
        if (file_exists($configPath)) {
            $allConfig = require $configPath;
            $this->config = $allConfig[$this->getId()] ?? [];
        }
    }

    /**
     * Set logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Log a message
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, array_merge([
                'service' => $this->getId(),
            ], $context));
        }
    }

    /**
     * Validate input against schema
     */
    protected function validateAgainstSchema(array $input, array $schema): array
    {
        $errors = [];
        
        // Check required fields
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $field) {
                if (!isset($input[$field]) || $input[$field] === '' || $input[$field] === null) {
                    $errors[$field] = "The {$field} field is required.";
                }
            }
        }
        
        // Validate properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($input as $key => $value) {
                if (!isset($schema['properties'][$key])) {
                    continue; // Allow additional properties
                }
                
                $property = $schema['properties'][$key];
                $fieldErrors = $this->validateProperty($key, $value, $property);
                
                if (!empty($fieldErrors)) {
                    $errors[$key] = $fieldErrors;
                }
            }
        }
        
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
        
        return $input;
    }

    /**
     * Validate a single property
     */
    private function validateProperty(string $key, mixed $value, array $schema): array
    {
        $errors = [];
        
        // Type validation
        if (isset($schema['type'])) {
            $type = $schema['type'];
            
            switch ($type) {
                case 'string':
                    if (!is_string($value)) {
                        $errors[] = "The {$key} must be a string.";
                    }
                    break;
                    
                case 'integer':
                    if (!is_int($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                        $errors[] = "The {$key} must be an integer.";
                    }
                    break;
                    
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[] = "The {$key} must be a number.";
                    }
                    break;
                    
                case 'boolean':
                    if (!is_bool($value)) {
                        $errors[] = "The {$key} must be a boolean.";
                    }
                    break;
                    
                case 'array':
                    if (!is_array($value)) {
                        $errors[] = "The {$key} must be an array.";
                    }
                    break;
                    
                case 'object':
                    if (!is_array($value) || array_is_list($value)) {
                        $errors[] = "The {$key} must be an object.";
                    }
                    break;
            }
        }
        
        // Enum validation
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            if (!in_array($value, $schema['enum'], true)) {
                $errors[] = "The {$key} must be one of: " . implode(', ', $schema['enum']) . '.';
            }
        }
        
        // String validations
        if (is_string($value)) {
            // Min length
            if (isset($schema['minLength']) && strlen($value) < $schema['minLength']) {
                $errors[] = "The {$key} must be at least {$schema['minLength']} characters.";
            }
            
            // Max length
            if (isset($schema['maxLength']) && strlen($value) > $schema['maxLength']) {
                $errors[] = "The {$key} must not exceed {$schema['maxLength']} characters.";
            }
            
            // Pattern
            if (isset($schema['pattern']) && !preg_match('/' . $schema['pattern'] . '/', $value)) {
                $errors[] = "The {$key} format is invalid.";
            }
            
            // Format
            if (isset($schema['format'])) {
                switch ($schema['format']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[] = "The {$key} must be a valid email address.";
                        }
                        break;
                        
                    case 'uri':
                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[] = "The {$key} must be a valid URL.";
                        }
                        break;
                }
            }
        }
        
        // Number validations
        if (is_numeric($value)) {
            // Minimum
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                $errors[] = "The {$key} must be at least {$schema['minimum']}.";
            }
            
            // Maximum
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                $errors[] = "The {$key} must not exceed {$schema['maximum']}.";
            }
        }
        
        return $errors;
    }

    /**
     * Report progress
     */
    protected function reportProgress(callable $callback, int $percent, ?string $message = null): void
    {
        if ($callback !== null) {
            $callback(min(100, max(0, $percent)), $message);
        }
    }

    /**
     * Create a temporary file
     */
    protected function createTempFile(?string $prefix = null, ?string $extension = null): string
    {
        $prefix = $prefix ?? $this->getId();
        $extension = $extension ? '.' . ltrim($extension, '.') : '';
        
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/' . $prefix . '_' . uniqid() . $extension;
        
        $this->tempFiles[] = $tempFile;
        
        return $tempFile;
    }

    /**
     * Create a temporary directory
     */
    protected function createTempDir(?string $prefix = null): string
    {
        $prefix = $prefix ?? $this->getId();
        
        $tempDir = sys_get_temp_dir() . '/' . $prefix . '_' . uniqid();
        mkdir($tempDir, 0755, true);
        
        $this->tempDirs[] = $tempDir;
        
        return $tempDir;
    }

    /**
     * Clean up temporary files and directories
     */
    protected function cleanup(): void
    {
        // Remove temp files
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Remove temp directories
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->recursiveDelete($dir);
            }
        }
        
        $this->tempFiles = [];
        $this->tempDirs = [];
    }

    /**
     * Recursively delete a directory
     */
    private function recursiveDelete(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }

    /**
     * Get config value
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get allowed file extensions
     */
    protected function getAllowedExtensions(): array
    {
        return $this->config('allowed_extensions', []);
    }

    /**
     * Get maximum file size
     */
    protected function getMaxFileSize(): int
    {
        return $this->config('max_file_size', 52428800); // 50MB default
    }

    /**
     * Validate file type
     */
    protected function validateFileType(string $path, array $allowedExtensions): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return in_array($extension, $allowedExtensions, true);
    }

    /**
     * Get MIME type from file
     */
    protected function getMimeType(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }
        
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        
        return $finfo->file($path);
    }

    /**
     * Generate a unique filename
     */
    protected function generateUniqueFilename(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(16));
        
        return $basename . ($extension ? '.' . $extension : '');
    }

    /**
     * Get service metadata (default implementation)
     */
    public function getMetadata(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'supports_chunking' => $this->supportsChunking(),
        ];
    }

    /**
     * Destructor - ensure cleanup
     */
    public function __destruct()
    {
        $this->cleanup();
    }
}
