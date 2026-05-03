<?php

declare(strict_types=1);

namespace DGLab\Services\MangaScript\AI\Providers;

use DGLab\Services\MangaScript\AI\LLMResponse;
use DGLab\Services\MangaScript\AI\LLMProviderException;

/**
 * xAI (Grok) LLM Provider
 *
 * Implements the xAI API for accessing Grok models.
 *
 * @package DGLab\Services\MangaScript\AI\Providers
 */
class XaiProvider extends AbstractLLMProvider
{
    /**
     * Provider identifier
     */
    protected string $providerId = 'xai';

    /**
     * Provider display name
     */
    protected string $providerName = 'xAI';

    /**
     * API base URL
     */
    protected string $apiBase = 'https://api.x.ai/v1';

    /**
     * Available models
     */
    protected array $availableModels = [
        'grok-2-1212' => [
            'context_tokens' => 131072,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'cost_input' => 0.002,
            'cost_output' => 0.01,
        ],
        'grok-2-mini-1212' => [
            'context_tokens' => 131072,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'cost_input' => 0.00015,
            'cost_output' => 0.0006,
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->providerId;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->providerName;
    }

    /**
     * {@inheritdoc}
     */
    public function getModels(): array
    {
        return $this->availableModels;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultModel(): string
    {
        return 'grok-2-1212';
    }

    /**
     * {@inheritdoc}
     */
    public function chat(string $model, array $messages, array $options = []): LLMResponse
    {
        $this->validateMessages($messages);

        $url = $this->apiBase . '/chat/completions';
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'stream' => false,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $this->getApiKey(),
            'Content-Type' => 'application/json',
        ];

        $startTime = microtime(true);
        try {
            $response = $this->httpRequest('POST', $url, $headers, $payload);
            $latency = (microtime(true) - $startTime) * 1000;

            $llmResponse = LLMResponse::fromOpenAIFormat($response, $this->providerId);
            $cost = $this->estimateCost($model, $llmResponse->inputTokens, $llmResponse->outputTokens);

            return new LLMResponse(
                content: $llmResponse->content,
                finishReason: $llmResponse->finishReason,
                inputTokens: $llmResponse->inputTokens,
                outputTokens: $llmResponse->outputTokens,
                modelUsed: $model,
                providerUsed: $this->providerId,
                latencyMs: $latency,
                costUsd: $cost
            );
        } catch (\Exception $e) {
            throw LLMProviderException::requestFailed($this->providerId, $model, $e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function chatStream(string $model, array $messages, array $options = []): \Generator
    {
        $this->validateMessages($messages);

        $response = $this->chat($model, $messages, $options);
        yield $response->content;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsStreaming(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsJsonMode(): bool
    {
        return true;
    }
}
