<?php

/**
 * DGLab LLM Provider Repository
 *
 * Central registry for all LLM providers.
 *
 * @package DGLab\Services\NovelToMangaScript\AI
 */

namespace DGLab\Services\NovelToMangaScript\AI;

use DGLab\Services\NovelToMangaScript\AI\Contracts\LLMProviderInterface;
use DGLab\Services\NovelToMangaScript\AI\Providers\AbstractLLMProvider;

/**
 * Class ProviderRepository
 *
 * Registry and factory for LLM providers.
 */
class ProviderRepository
{
    /**
     * Registered providers
     * @var array<string, array>
     */
    private array $providers = [];

    /**
     * Provider instances (lazy-loaded)
     * @var array<string, LLMProviderInterface>
     */
    private array $instances = [];

    /**
     * Provider configuration
     */
    private array $config;

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->registerBuiltInProviders();
    }

    /**
     * Register built-in providers
     */
    private function registerBuiltInProviders(): void
    {
        $this->providers = [
            // Category A: Enterprise Cloud
            'openai' => [
                'class' => Providers\OpenAiProvider::class,
                'category' => 'A',
                'tier' => 1,
                'display_name' => 'OpenAI',
                'censorship_default' => 'configurable',
                'deployment_type' => 'saas',
                'compliance' => ['soc2', 'gdpr', 'ccpa', 'hipaa_baa_available'],
            ],
            'anthropic' => [
                'class' => Providers\AnthropicProvider::class,
                'category' => 'A',
                'tier' => 1,
                'display_name' => 'Anthropic',
                'censorship_default' => 'censored',
                'deployment_type' => 'saas',
                'compliance' => ['soc2', 'gdpr', 'ccpa', 'iso27001'],
            ],
            'google_gemini' => [
                'class' => Providers\GoogleProvider::class,
                'category' => 'A',
                'tier' => 1,
                'display_name' => 'Google Gemini',
                'censorship_default' => 'configurable',
                'deployment_type' => 'saas',
                'compliance' => ['soc2', 'gdpr', 'fedramp', 'iso27001'],
            ],

            // Category B: Open Model Hosting
            'mistral' => [
                'class' => Providers\MistralProvider::class,
                'category' => 'B',
                'tier' => 2,
                'display_name' => 'Mistral AI',
                'censorship_default' => 'minimal',
                'deployment_type' => 'saas',
                'compliance' => ['gdpr', 'iso27001'],
            ],
            'together' => [
                'class' => Providers\TogetherProvider::class,
                'category' => 'B',
                'tier' => 2,
                'display_name' => 'Together AI',
                'censorship_default' => 'uncensored',
                'deployment_type' => 'saas',
                'compliance' => ['soc2'],
            ],

            // Category C: Aggregators
            'openrouter' => [
                'class' => Providers\OpenRouterProvider::class,
                'category' => 'C',
                'tier' => 2,
                'display_name' => 'OpenRouter',
                'censorship_default' => 'varies',
                'deployment_type' => 'saas',
                'features' => ['fallback_routing', 'price_optimization'],
            ],

            // Category D: Regional
            'deepseek' => [
                'class' => Providers\DeepSeekProvider::class,
                'category' => 'D',
                'tier' => 2,
                'display_name' => 'DeepSeek',
                'region' => 'CN',
                'censorship_default' => 'regulatory',
                'deployment_type' => 'saas',
                'cost_advantage' => '90% cheaper',
            ],

            // Category E: Local/Self-hosted
            'ollama' => [
                'class' => Providers\OllamaProvider::class,
                'category' => 'E',
                'tier' => 3,
                'display_name' => 'Ollama',
                'censorship_default' => 'uncensored',
                'deployment_type' => 'local',
                'cost_model' => 'hardware_only',
            ],
        ];
    }

    /**
     * Register a custom provider
     */
    public function register(string $id, array $definition): self
    {
        $this->providers[$id] = $definition;
        return $this;
    }

    /**
     * Get provider instance
     */
    public function get(string $id): LLMProviderInterface
    {
        if (!isset($this->providers[$id])) {
            throw new \InvalidArgumentException("Unknown provider: {$id}");
        }

        if (!isset($this->instances[$id])) {
            $definition = $this->providers[$id];
            $class = $definition['class'];

            $providerConfig = array_merge(
                $definition,
                $this->config['providers'][$id] ?? []
            );

            $this->instances[$id] = new $class($providerConfig);
        }

        return $this->instances[$id];
    }

    /**
     * Check if provider exists
     */
    public function has(string $id): bool
    {
        return isset($this->providers[$id]);
    }

    /**
     * Get all provider definitions
     */
    public function getAll(): array
    {
        return $this->providers;
    }

    /**
     * Get providers by category
     */
    public function getByCategory(string $category): array
    {
        return array_filter(
            $this->providers,
            fn($p) => ($p['category'] ?? '') === $category
        );
    }

    /**
     * Get providers by tier
     */
    public function getByTier(int $tier): array
    {
        return array_filter(
            $this->providers,
            fn($p) => ($p['tier'] ?? 0) === $tier
        );
    }

    /**
     * Get providers supporting uncensored mode
     */
    public function getUncensoredProviders(): array
    {
        return array_filter(
            $this->providers,
            fn($p) => in_array($p['censorship_default'] ?? '', ['uncensored', 'minimal', 'configurable'])
        );
    }

    /**
     * Get free providers
     */
    public function getFreeProviders(): array
    {
        return array_filter(
            $this->providers,
            fn($p) => ($p['cost_model'] ?? '') === 'hardware_only' || isset($p['free_tier'])
        );
    }

    /**
     * Get local providers
     */
    public function getLocalProviders(): array
    {
        return array_filter(
            $this->providers,
            fn($p) => ($p['deployment_type'] ?? '') === 'local'
        );
    }

    /**
     * Get provider metadata for UI
     */
    public function getProviderMetadata(): array
    {
        $metadata = [];

        foreach ($this->providers as $id => $definition) {
            try {
                $provider = $this->get($id);
                $metadata[$id] = array_merge($definition, $provider->getMetadata());
            } catch (\Throwable $e) {
                $metadata[$id] = array_merge($definition, [
                    'available' => false,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $metadata;
    }

    /**
     * Get provider count
     */
    public function count(): int
    {
        return count($this->providers);
    }
}
