<?php
/**
 * DGLab PWA - AI Service Factory
 * 
 * Factory for creating AI service instances.
 * 
 * @package DGLab\Tools\NovelToManga
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\NovelToManga;

/**
 * AiServiceFactory Class
 * 
 * Creates AI service instances based on provider.
 */
class AiServiceFactory
{
    /**
     * @var array $services Registered service classes
     */
    private array $services = [
        'openai' => OpenAiService::class,
        'claude' => ClaudeService::class,
        'gemini' => GeminiService::class,
    ];

    /**
     * @var array $defaultApiKeys Default API keys from config
     */
    private array $defaultApiKeys = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Load default API keys from environment or config
        $this->defaultApiKeys = [
            'openai' => $_ENV['OPENAI_API_KEY'] ?? null,
            'claude' => $_ENV['CLAUDE_API_KEY'] ?? null,
            'gemini' => $_ENV['GEMINI_API_KEY'] ?? null,
        ];
    }

    /**
     * Create AI service instance
     * 
     * @param string $provider Provider name
     * @param string $model Model name
     * @param string|null $apiKey Custom API key (optional)
     * @return AiServiceInterface AI service instance
     * @throws \Exception If provider not supported
     */
    public function create(string $provider, string $model, ?string $apiKey = null): AiServiceInterface
    {
        $provider = strtolower($provider);
        
        if (!isset($this->services[$provider])) {
            throw new \Exception("Unsupported AI provider: {$provider}");
        }
        
        $serviceClass = $this->services[$provider];
        
        // Use custom API key or fall back to default
        $key = $apiKey ?? $this->defaultApiKeys[$provider] ?? null;
        
        if ($key === null && $provider !== 'openai') {
            throw new \Exception("API key required for {$provider}");
        }
        
        return new $serviceClass($model, $key);
    }

    /**
     * Register a custom service
     * 
     * @param string $name Service name
     * @param string $className Service class name
     * @return self For method chaining
     */
    public function registerService(string $name, string $className): self
    {
        if (!class_exists($className)) {
            throw new \Exception("Service class not found: {$className}");
        }
        
        if (!in_array(AiServiceInterface::class, class_implements($className), true)) {
            throw new \Exception("Service must implement AiServiceInterface");
        }
        
        $this->services[strtolower($name)] = $className;
        
        return $this;
    }

    /**
     * Get available providers
     * 
     * @return array Provider names
     */
    public function getAvailableProviders(): array
    {
        $available = [];
        
        foreach ($this->services as $name => $class) {
            // Check if provider has default key or is free tier
            if ($name === 'openai' || !empty($this->defaultApiKeys[$name])) {
                $available[] = $name;
            }
        }
        
        return $available;
    }

    /**
     * Get available models for provider
     * 
     * @param string $provider Provider name
     * @return array Model names
     */
    public function getAvailableModels(string $provider): array
    {
        $models = [
            'openai' => [
                'gpt-4o'        => 'GPT-4o (Best Quality)',
                'gpt-4o-mini'   => 'GPT-4o Mini (Fast & Cheap)',
                'gpt-4-turbo'   => 'GPT-4 Turbo',
            ],
            'claude' => [
                'claude-3-opus'   => 'Claude 3 Opus',
                'claude-3-sonnet' => 'Claude 3 Sonnet',
                'claude-3-haiku'  => 'Claude 3 Haiku',
            ],
            'gemini' => [
                'gemini-1.5-pro'  => 'Gemini 1.5 Pro',
                'gemini-1.5-flash'=> 'Gemini 1.5 Flash',
            ],
        ];
        
        return $models[strtolower($provider)] ?? [];
    }

    /**
     * Check if provider supports free tier
     * 
     * @param string $provider Provider name
     * @return bool True if free tier available
     */
    public function hasFreeTier(string $provider): bool
    {
        return strtolower($provider) === 'openai';
    }
}
