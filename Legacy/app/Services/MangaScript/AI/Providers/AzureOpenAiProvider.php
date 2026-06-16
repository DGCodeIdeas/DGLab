<?php

/**
 * DGLab Azure OpenAI Provider
 *
 * Implementation for Azure OpenAI Service.
 *
 * @package DGLab\Services\MangaScript\AI\Providers
 */

namespace DGLab\Services\MangaScript\AI\Providers;

use DGLab\Services\MangaScript\AI\LLMResponse;

/**
 * Class AzureOpenAiProvider
 *
 * Azure OpenAI Service provider implementation.
 */
class AzureOpenAiProvider extends AbstractLLMProvider
{
    /**
     * Provider ID
     */
    private const PROVIDER_ID = 'azure';

    /**
     * Provider display name
     */
    private const PROVIDER_NAME = 'Azure OpenAI';

    /**
     * Available models (mapped to Azure deployment names)
     */
    private const MODELS = [
        'gpt-4o' => [
            'censorship_level' => 3,
            'cost_input' => 0.005,
            'cost_output' => 0.015,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'analytical'],
        ],
        'gpt-4o-mini' => [
            'censorship_level' => 3,
            'cost_input' => 0.00015,
            'cost_output' => 0.0006,
            'context_tokens' => 128000,
            'speed_tier' => 'ultra',
            'specializations' => ['general'],
        ],
    ];

    /**
     * Get provider ID
     */
    public function getId(): string
    {
        return self::PROVIDER_ID;
    }

    /**
     * Get display name
     */
    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    /**
     * Get available models
     */
    public function getModels(): array
    {
        return self::MODELS;
    }

    /**
     * Check if streaming is supported
     */
    public function supportsStreaming(): bool
    {
        return true;
    }

    /**
     * Check if JSON mode is supported
     */
    public function supportsJsonMode(): bool
    {
        return true;
    }

    /**
     * Get default model
     */
    protected function getDefaultModel(): string
    {
        return 'gpt-4o-mini';
    }

    /**
     * Execute chat completion
     */
    public function chat(string $model, array $messages, array $options = []): LLMResponse
    {
        $this->validateMessages($messages);

        $deploymentId = $this->config['deployments'][$model] ?? $model;
        $apiVersion = $this->config['api_version'] ?? '2024-02-15-preview';

        $url = rtrim($this->getApiBase(), '/') . "/openai/deployments/{$deploymentId}/chat/completions?api-version={$apiVersion}";

        $body = [
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        if ($options['json_mode'] ?? false) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $headers = [
            'api-key' => $this->getApiKey(),
            'Content-Type' => 'application/json',
        ];

        $start = microtime(true);
        $response = $this->httpRequest('POST', $url, $headers, $body);
        $latency = (microtime(true) - $start) * 1000;

        $llmResponse = LLMResponse::fromOpenAIFormat(
            $response,
            self::PROVIDER_ID,
            $this->currentMode === 'uncensored'
        );

        $cost = $this->estimateCost($model, $llmResponse->inputTokens, $llmResponse->outputTokens);

        return new LLMResponse(
            content: $llmResponse->content,
            finishReason: $llmResponse->finishReason,
            inputTokens: $llmResponse->inputTokens,
            outputTokens: $llmResponse->outputTokens,
            modelUsed: $llmResponse->modelUsed,
            providerUsed: $llmResponse->providerUsed,
            metadata: $llmResponse->metadata,
            isUncensored: $llmResponse->isUncensored,
            latencyMs: $latency,
            costUsd: $cost
        );
    }

    /**
     * Execute streaming chat completion
     */
    public function chatStream(string $model, array $messages, array $options = []): \Generator
    {
        // Basic implementation for CI stability
        yield "Azure OpenAI streaming not fully implemented in mock.";
    }
}
