<?php

declare(strict_types=1);

namespace DGLab\Services\MangaScript\AI\Providers;

use DGLab\Services\MangaScript\AI\LLMResponse;
use DGLab\Services\MangaScript\AI\LLMProviderException;

/**
 * Groq LLM Provider
 *
 * Implements the Groq Cloud API for high-speed inference of
 * Llama 3, Mixtral, and Gemma models.
 *
 * @package DGLab\Services\MangaScript\AI\Providers
 */
class GroqProvider extends AbstractLLMProvider
{
    /**
     * Provider identifier
     */
    protected string $providerId = 'groq';

    /**
     * Provider display name
     */
    protected string $providerName = 'Groq';

    /**
     * API base URL
     */
    protected string $apiBase = 'https://api.groq.com/openai/v1';

    /**
     * Available models on Groq
     */
    protected array $availableModels = [
        'llama-3.3-70b-versatile' => [
            'context_tokens' => 128000,
            'max_output' => 32768,
            'supports_json_mode' => true,
            'cost_input' => 0.00059,
            'cost_output' => 0.00079,
        ],
        'mixtral-8x7b-32768' => [
            'context_tokens' => 32768,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'cost_input' => 0.00024,
            'cost_output' => 0.00024,
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
        return 'llama-3.3-70b-versatile';
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
