<?php

declare(strict_types=1);

namespace DGLab\Services\MangaScript\AI;

use DGLab\Services\MangaScript\AI\Contracts\LLMProviderInterface;
use DGLab\Services\MangaScript\AI\Providers\{
    OpenAiProvider,
    AnthropicProvider,
    GoogleProvider,
    MistralProvider,
    TogetherProvider,
    OpenRouterProvider,
    OllamaProvider,
    DeepSeekProvider,
    GroqProvider,
    CohereProvider,
    XaiProvider,
    BedrockProvider,
    AzureOpenAiProvider,
    CustomProvider
};

/**
 * LLM Provider Factory
 *
 * Factory class for creating and managing LLM provider instances.
 * Handles provider registration, instantiation, and configuration.
 *
 * @package DGLab\Services\MangaScript\AI
 */
class ProviderFactory
{
    /**
     * Registered provider class mappings
     */
    protected static array $providers = [
        'openai' => OpenAiProvider::class,
        'anthropic' => AnthropicProvider::class,
        'google' => GoogleProvider::class,
        'mistral' => MistralProvider::class,
        'together' => TogetherProvider::class,
        'openrouter' => OpenRouterProvider::class,
        'ollama' => OllamaProvider::class,
        'deepseek' => DeepSeekProvider::class,
        'groq' => GroqProvider::class,
        'cohere' => CohereProvider::class,
        'xai' => XaiProvider::class,
        'bedrock' => BedrockProvider::class,
        'azure_openai' => AzureOpenAiProvider::class,
    ];

    /**
     * Cached provider instances
     */
    protected array $instances = [];

    /**
     * Configuration array
     */
    protected array $config;

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Create a provider instance
     *
     * @param string $providerId Provider identifier
     * @param string|null $apiKey API key (optional, uses config if not provided)
     * @param array $providerConfig Additional provider configuration
     * @return LLMProviderInterface
     * @throws LLMProviderException If provider not found or initialization fails
     */
    public function create(
        string $providerId,
        ?string $apiKey = null,
        array $providerConfig = []
    ): LLMProviderInterface {
        $providerId = strtolower($providerId);

        // Check for custom provider
        if (str_starts_with($providerId, 'custom_') || isset($providerConfig['custom'])) {
            return $this->createCustomProvider($providerId, $apiKey, $providerConfig);
        }

        // Check if provider is registered
        if (!isset(self::$providers[$providerId])) {
            throw LLMProviderException::providerNotFound(
                $providerId,
                array_keys(self::$providers)
            );
        }

        // Get API key from config if not provided
        if ($apiKey === null) {
            $apiKey = $this->getApiKeyForProvider($providerId);
        }

        // Merge configurations
        $config = array_merge(
            $this->config['providers'][$providerId] ?? [],
            $providerConfig
        );

        // Create provider instance
        $providerClass = self::$providers[$providerId];

        try {
            return new $providerClass($apiKey, $config);
        } catch (\Exception $e) {
            throw LLMProviderException::initializationFailed(
                $providerId,
                $e->getMessage()
            );
        }
    }

    /**
     * Get or create a cached provider instance
     *
     * @param string $providerId Provider identifier
     * @param string|null $apiKey API key
     * @param array $providerConfig Provider configuration
     * @return LLMProviderInterface
     */
    public function get(
        string $providerId,
        ?string $apiKey = null,
        array $providerConfig = []
    ): LLMProviderInterface {
        $cacheKey = $this->getCacheKey($providerId, $apiKey, $providerConfig);

        if (!isset($this->instances[$cacheKey])) {
            $this->instances[$cacheKey] = $this->create($providerId, $apiKey, $providerConfig);
        }

        return $this->instances[$cacheKey];
    }

    /**
     * Create a custom provider instance
     */
    protected function createCustomProvider(
        string $providerId,
        ?string $apiKey,
        array $providerConfig
    ): LLMProviderInterface {
        // Validate custom provider configuration
        if (empty($providerConfig['endpoint'])) {
            throw new \InvalidArgumentException(
                "Custom provider '{$providerId}' requires an 'endpoint' configuration"
            );
        }

        return new CustomProvider($apiKey ?? '', array_merge(
            ['provider_id' => $providerId],
            $providerConfig
        ));
    }

    /**
     * Get API key for a provider from configuration
     */
    protected function getApiKeyForProvider(string $providerId): string
    {
        // Check provider-specific config first
        if (!empty($this->config['providers'][$providerId]['api_key'])) {
            return $this->config['providers'][$providerId]['api_key'];
        }

        // Check environment variables
        $envKeyMap = [
            'openai' => 'OPENAI_API_KEY',
            'anthropic' => 'ANTHROPIC_API_KEY',
            'google' => 'GOOGLE_AI_API_KEY',
            'mistral' => 'MISTRAL_API_KEY',
            'together' => 'TOGETHER_API_KEY',
            'openrouter' => 'OPENROUTER_API_KEY',
            'deepseek' => 'DEEPSEEK_API_KEY',
            'groq' => 'GROQ_API_KEY',
            'cohere' => 'COHERE_API_KEY',
            'xai' => 'XAI_API_KEY',
            'azure_openai' => 'AZURE_OPENAI_API_KEY',
        ];

        if (isset($envKeyMap[$providerId])) {
            $envKey = getenv($envKeyMap[$providerId]);
            if ($envKey !== false && $envKey !== '') {
                return $envKey;
            }
        }

        // For Ollama and Bedrock (uses IAM), no API key required
        if (in_array($providerId, ['ollama', 'bedrock'])) {
            return '';
        }

        throw LLMProviderException::missingApiKey($providerId);
    }

    /**
     * Generate cache key for provider instance
     */
    protected function getCacheKey(string $providerId, ?string $apiKey, array $config): string
    {
        return md5($providerId . ($apiKey ?? '') . json_encode($config));
    }

    /**
     * Register a new provider class
     *
     * @param string $providerId Provider identifier
     * @param string $providerClass Fully qualified class name
     */
    public static function register(string $providerId, string $providerClass): void
    {
        if (!is_subclass_of($providerClass, LLMProviderInterface::class)) {
            throw new \InvalidArgumentException(
                "Provider class must implement LLMProviderInterface"
            );
        }

        self::$providers[strtolower($providerId)] = $providerClass;
    }

    /**
     * Get all registered provider identifiers
     *
     * @return array<string>
     */
    public static function getRegisteredProviders(): array
    {
        return array_keys(self::$providers);
    }

    /**
     * Check if a provider is registered
     */
    public static function isRegistered(string $providerId): bool
    {
        return isset(self::$providers[strtolower($providerId)]);
    }

    /**
     * Get provider class for a provider ID
     */
    public static function getProviderClass(string $providerId): ?string
    {
        return self::$providers[strtolower($providerId)] ?? null;
    }

    /**
     * Clear cached instances
     */
    public function clearCache(): void
    {
        $this->instances = [];
    }

    /**
     * Get all providers with their availability status
     *
     * @return array<string, array{available: bool, reason: string|null}>
     */
    public function getProviderAvailability(): array
    {
        $availability = [];

        foreach (self::$providers as $providerId => $class) {
            try {
                $apiKey = $this->getApiKeyForProvider($providerId);
                $availability[$providerId] = [
                    'available' => true,
                    'reason' => null,
                    'has_api_key' => !empty($apiKey),
                ];
            } catch (\Exception $e) {
                $availability[$providerId] = [
                    'available' => false,
                    'reason' => $e->getMessage(),
                    'has_api_key' => false,
                ];
            }
        }

        return $availability;
    }

    /**
     * Create multiple providers at once
     *
     * @param array<string> $providerIds List of provider identifiers
     * @return array<string, LLMProviderInterface>
     */
    public function createMultiple(array $providerIds): array
    {
        $providers = [];

        foreach ($providerIds as $providerId) {
            try {
                $providers[$providerId] = $this->get($providerId);
            } catch (\Exception $e) {
                // Skip providers that fail to initialize
                continue;
            }
        }

        return $providers;
    }

    /**
     * Get provider capabilities summary
     *
     * @return array<string, array>
     */
    public function getCapabilitiesSummary(): array
    {
        $summary = [];

        foreach (self::$providers as $providerId => $class) {
            try {
                $provider = $this->get($providerId);
                $summary[$providerId] = [
                    'name' => $provider->getProviderName(),
                    'streaming' => $provider->supportsStreaming(),
                    'json_mode' => $provider->supportsJsonMode(),
                    'function_calling' => $provider->supportsFunctionCalling(),
                    'vision' => $provider->supportsVision(),
                    'models' => $provider->getAvailableModels(),
                ];
            } catch (\Exception $e) {
                $summary[$providerId] = [
                    'name' => $providerId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $summary;
    }


    /**
     * Reset the factory to its default state
     */
    public static function reset(): void
    {
        self::$providers = [
        'openai' => OpenAiProvider::class,
        'anthropic' => AnthropicProvider::class,
        'google' => GoogleProvider::class,
        'mistral' => MistralProvider::class,
        'together' => TogetherProvider::class,
        'openrouter' => OpenRouterProvider::class,
        'ollama' => OllamaProvider::class,
        'deepseek' => DeepSeekProvider::class,
        'groq' => GroqProvider::class,
        'cohere' => CohereProvider::class,
        'xai' => XaiProvider::class,
        'bedrock' => BedrockProvider::class,
        'azure_openai' => AzureOpenAiProvider::class,
    ];
    }
}
