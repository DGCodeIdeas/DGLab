<?php

declare(strict_types=1);

namespace DGLab\Services\MangaScript\AI\Providers;

use DGLab\Services\MangaScript\AI\LLMResponse;
use DGLab\Services\MangaScript\AI\LLMProviderException;

/**
 * Cohere LLM Provider
 *
 * Implements the Cohere API (v2) for Command R+ and Command R models.
 *
 * @package DGLab\Services\MangaScript\AI\Providers
 */
class CohereProvider extends AbstractLLMProvider
{
    /**
     * Provider identifier
     */
    protected string $providerId = 'cohere';

    /**
     * Provider display name
     */
    protected string $providerName = 'Cohere';

    /**
     * Default API endpoint
     */
    protected string $defaultEndpoint = 'https://api.cohere.com/v2/chat';

    /**
     * Available models
     */
    protected array $availableModels = [
        'command-r-plus' => [
            'context_tokens' => 128000,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'cost_input' => 0.003,
            'cost_output' => 0.015,
        ],
        'command-r' => [
            'context_tokens' => 128000,
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
        return 'command-r-plus';
    }

    /**
     * {@inheritdoc}
     */
    public function chat(string $model, array $messages, array $options = []): LLMResponse
    {
        $this->validateMessages($messages);

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
            'X-Client-Name: DGLab-NovelToMangaScript',
        ];

        $startTime = microtime(true);
        try {
            $response = $this->httpRequest('POST', $this->defaultEndpoint, $headers, $payload);
            $latency = (microtime(true) - $startTime) * 1000;

            return $this->parseCohereResponse($response, $model, $latency);
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

        // Simplified streaming placeholder
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

    /**
     * Parse Cohere API response (v2 format)
     */
    protected function parseCohereResponse(array $response, string $model, float $latency): LLMResponse
    {
        $content = '';
        if (isset($response['message']['content'])) {
            foreach ($response['message']['content'] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }
        }

        $usage = $response['usage'] ?? [];
        $inputTokens = $usage['tokens']['input_tokens'] ?? 0;
        $outputTokens = $usage['tokens']['output_tokens'] ?? 0;

        $cost = $this->estimateCost($model, $inputTokens, $outputTokens);

        return new LLMResponse(
            content: $content,
            finishReason: $response['finish_reason'] ?? 'stop',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            modelUsed: $model,
            providerUsed: $this->providerId,
            latencyMs: $latency,
            costUsd: $cost
        );
    }
}
