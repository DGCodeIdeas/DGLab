<?php

namespace DGLab\Services\MangaScript\AI;

use DGLab\Core\Application;
use DGLab\Services\MangaScript\AI\LLMProviderException;

/**
 * MangaScript Routing Engine 3.0
 *
 * Uses llm_unified.php and llm_categorization.php for dynamic model selection.
 */
class RoutingEngine
{
    protected Application $app;
    protected array $providers;
    protected array $categories;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->providers = config('llm_unified.providers');
        $this->categories = config('llm_categorization.provider_categories');
    }

    /**
     * Route a request to the most suitable LLM
     */
    public function route(array $input): RoutingResponse
    {
        $category = $input['category'] ?? 'A'; // Enterprise Cloud by default
        $tier = $input['tier'] ?? 'medium';

        $models = $this->getModelsForCategoryAndTier($category, $tier);

        if (empty($models)) {
            throw new LLMProviderException("No suitable model found for category [{$category}] and tier [{$tier}]");
        }

        $model = $models[0]; // Select the first available for now

        return new RoutingResponse($model, $input);
    }

    protected function getModelsForCategoryAndTier(string $category, string $tier): array
    {
        $results = [];

        foreach ($this->providers as $providerId => $config) {
            if ($config['category'] === $category) {
                foreach ($config['models'] as $modelId => $modelConfig) {
                    if (isset($modelConfig['context_tier']) && $modelConfig['context_tier'] === $tier) {
                        $results[] = [
                            'provider' => $providerId,
                            'model' => $modelId,
                            'config' => $modelConfig
                        ];
                    }
                }
            }
        }

        return $results;
    }
}
