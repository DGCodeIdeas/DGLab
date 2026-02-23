<?php
/**
 * DGLab PWA - Tool Registry
 * 
 * The ToolRegistry manages all available tools in the system.
 * It provides:
 * - Tool registration and discovery
 * - Tool metadata access
 * - Tool instantiation
 * - Category management
 * 
 * @package DGLab\Tools
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools;

use DGLab\Tools\Interfaces\ToolInterface;

/**
 * ToolRegistry Class
 * 
 * Central registry for all tools in the DGLab PWA.
 */
class ToolRegistry
{
    /**
     * @var array $tools Registered tools
     */
    private array $tools = [];
    
    /**
     * @var array $categories Tool categories
     */
    private array $categories = [];
    
    /**
     * @var ToolRegistry|null $instance Singleton instance
     */
    private static ?ToolRegistry $instance = null;

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->discoverTools();
    }

    /**
     * Get singleton instance
     * 
     * @return ToolRegistry Registry instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Register a tool
     * 
     * @param ToolInterface $tool Tool instance
     * @return self For method chaining
     * @throws \Exception If tool ID already registered
     */
    public function register(ToolInterface $tool): self
    {
        $id = $tool->getId();
        
        if (isset($this->tools[$id])) {
            throw new \Exception("Tool already registered: {$id}");
        }
        
        $this->tools[$id] = $tool;
        
        // Register category
        $category = $tool->getCategory();
        if (!isset($this->categories[$category])) {
            $this->categories[$category] = [];
        }
        $this->categories[$category][] = $id;
        
        return $this;
    }

    /**
     * Unregister a tool
     * 
     * @param string $id Tool ID
     * @return self For method chaining
     */
    public function unregister(string $id): self
    {
        if (isset($this->tools[$id])) {
            $tool = $this->tools[$id];
            $category = $tool->getCategory();
            
            // Remove from category
            if (isset($this->categories[$category])) {
                $key = array_search($id, $this->categories[$category], true);
                if ($key !== false) {
                    unset($this->categories[$category][$key]);
                }
                
                // Remove empty category
                if (empty($this->categories[$category])) {
                    unset($this->categories[$category]);
                }
            }
            
            unset($this->tools[$id]);
        }
        
        return $this;
    }

    /**
     * Get a tool by ID
     * 
     * @param string $id Tool ID
     * @return ToolInterface|null Tool instance or null
     */
    public function get(string $id): ?ToolInterface
    {
        return $this->tools[$id] ?? null;
    }

    /**
     * Check if tool is registered
     * 
     * @param string $id Tool ID
     * @return bool True if registered
     */
    public function has(string $id): bool
    {
        return isset($this->tools[$id]);
    }

    /**
     * Get all registered tools
     * 
     * @return array All tools
     */
    public function getAll(): array
    {
        return $this->tools;
    }

    /**
     * Get all tool IDs
     * 
     * @return array Tool IDs
     */
    public function getIds(): array
    {
        return array_keys($this->tools);
    }

    /**
     * Get tools by category
     * 
     * @param string $category Category name
     * @return array Tools in category
     */
    public function getByCategory(string $category): array
    {
        if (!isset($this->categories[$category])) {
            return [];
        }
        
        $tools = [];
        foreach ($this->categories[$category] as $id) {
            $tools[$id] = $this->tools[$id];
        }
        
        return $tools;
    }

    /**
     * Get all categories
     * 
     * @return array Category names
     */
    public function getCategories(): array
    {
        return array_keys($this->categories);
    }

    /**
     * Get tools grouped by category
     * 
     * @return array Tools grouped by category
     */
    public function getGroupedByCategory(): array
    {
        $grouped = [];
        
        foreach ($this->categories as $category => $toolIds) {
            $grouped[$category] = [];
            foreach ($toolIds as $id) {
                $grouped[$category][$id] = $this->tools[$id];
            }
        }
        
        return $grouped;
    }

    /**
     * Get tool metadata
     * 
     * @param string $id Tool ID
     * @return array|null Tool metadata
     */
    public function getMetadata(string $id): ?array
    {
        $tool = $this->get($id);
        
        if ($tool === null) {
            return null;
        }
        
        return [
            'id'               => $tool->getId(),
            'name'             => $tool->getName(),
            'description'      => $tool->getDescription(),
            'icon'             => $tool->getIcon(),
            'category'         => $tool->getCategory(),
            'supported_types'  => $tool->getSupportedTypes(),
            'max_file_size'    => $tool->getMaxFileSize(),
            'supports_chunking'=> $tool->supportsChunking(),
            'config_schema'    => $tool->getConfigSchema(),
        ];
    }

    /**
     * Get all tools metadata
     * 
     * @return array All tools metadata
     */
    public function getAllMetadata(): array
    {
        $metadata = [];
        
        foreach ($this->tools as $id => $tool) {
            $metadata[$id] = $this->getMetadata($id);
        }
        
        return $metadata;
    }

    /**
     * Search tools by name or description
     * 
     * @param string $query Search query
     * @return array Matching tools
     */
    public function search(string $query): array
    {
        $results = [];
        $query = strtolower($query);
        
        foreach ($this->tools as $id => $tool) {
            $name = strtolower($tool->getName());
            $description = strtolower($tool->getDescription());
            
            if (strpos($name, $query) !== false || strpos($description, $query) !== false) {
                $results[$id] = $tool;
            }
        }
        
        return $results;
    }

    /**
     * Count registered tools
     * 
     * @return int Number of tools
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Clear all registered tools
     * 
     * @return self For method chaining
     */
    public function clear(): self
    {
        $this->tools = [];
        $this->categories = [];
        
        return $this;
    }

    // =============================================================================
    // DISCOVERY METHODS
    // =============================================================================

    /**
     * Discover and auto-register tools
     * 
     * @return void
     */
    private function discoverTools(): void
    {
        $toolsPath = __DIR__;
        
        // Scan for tool directories
        $directories = glob($toolsPath . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $toolName = basename($dir);
            
            // Skip non-tool directories
            if ($toolName === 'Interfaces' || $toolName === 'Traits') {
                continue;
            }
            
            // Look for main tool class
            $classFile = $dir . '/' . $toolName . '.php';
            
            if (file_exists($classFile)) {
                $this->autoRegister($toolName);
            }
        }
    }

    /**
     * Auto-register a tool by name
     * 
     * @param string $toolName Tool class name
     * @return bool True if registered
     */
    public function autoRegister(string $toolName): bool
    {
        $className = 'DGLab\\Tools\\' . $toolName . '\\' . $toolName;
        
        if (!class_exists($className)) {
            return false;
        }
        
        // Check if implements interface
        $interfaces = class_implements($className);
        if (!in_array(ToolInterface::class, $interfaces, true)) {
            return false;
        }
        
        try {
            $tool = new $className();
            $this->register($tool);
            return true;
        } catch (\Exception $e) {
            error_log("Failed to register tool {$toolName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Manually register a tool by class name
     * 
     * @param string $className Full class name
     * @return bool True if registered
     */
    public function registerClass(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }
        
        $interfaces = class_implements($className);
        if (!in_array(ToolInterface::class, $interfaces, true)) {
            return false;
        }
        
        try {
            $tool = new $className();
            $this->register($tool);
            return true;
        } catch (\Exception $e) {
            error_log("Failed to register tool {$className}: " . $e->getMessage());
            return false;
        }
    }
}
