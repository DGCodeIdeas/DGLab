<?php

/**
 * DGLab OpenAI Provider
 *
 * Implementation for OpenAI API (GPT-4, o1, o3-mini models).
 *
 * @package DGLab\Services\NovelToMangaScript\AI\Providers
 */

namespace DGLab\Services\NovelToMangaScript\AI\Providers;

use DGLab\Services\NovelToMangaScript\AI\LLMResponse;

/**
 * Class OpenAiProvider
 *
 * OpenAI API provider implementation.
 */
class OpenAiProvider extends AbstractLLMProvider
{
    /**
     * Provider ID
     */
    private const PROVIDER_ID = 'openai';

    /**
     * Provider display name
     */
    private const PROVIDER_NAME = 'OpenAI';

    /**
     * API base URL
     */
    private const API_BASE = 'https://api.openai.com/v1';

    /**
     * Available models
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
        'gpt-4-turbo' => [
            'censorship_level' => 3,
            'cost_input' => 0.01,
            'cost_output' => 0.03,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'creative'],
        ],
        'o1-preview' => [
            'censorship_level' => 3,
            'cost_input' => 0.015,
            'cost_output' => 0.06,
            'context_tokens' => 128000,
            'speed_tier' => 'batch',
            'specializations' => ['analytical', 'technical'],
        ],
        'o1' => [
            'censorship_level' => 3,
            'cost_input' => 0.005,
            'cost_output' => 0.015,
            'context_tokens' => 200000,
            'speed_tier' => 'batch',
            'specializations' => ['analytical', 'technical'],
        ],
        'o3-mini' => [
            'censorship_level' => 3,
            'cost_input' => 0.0011,
            'cost_output' => 0.0044,
            'context_tokens' => 200000,
            'speed_tier' => 'fast',
            'specializations' => ['analytical', 'technical'],
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

        $url = $this->getApiBase() . '/chat/completions';

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        // Add JSON mode if requested
        if ($options['json_mode'] ?? false) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        // Add stop sequences if provided
        if (!empty($options['stop'])) {
            $body['stop'] = $options['stop'];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->getApiKey(),
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

        // Calculate cost
        $cost = $this->estimateCost(
            $model,
            $llmResponse->inputTokens,
            $llmResponse->outputTokens
        );

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
        $this->validateMessages($messages);

        $url = $this->getApiBase() . '/chat/completions';

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'stream' => true,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->getApiKey(),
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$buffer) {
                $buffer .= $data;
                return strlen($data);
            },
        ]);

        $buffer = '';
        curl_exec($ch);
        curl_close($ch);

        // Parse SSE stream
        $lines = explode("\n", $buffer);
        foreach ($lines as $line) {
            if (str_starts_with($line, 'data: ')) {
                $json = substr($line, 6);
                if ($json === '[DONE]') {
                    break;
                }

                $data = json_decode($json, true);
                if ($data && isset($data['choices'][0]['delta']['content'])) {
                    yield $data['choices'][0]['delta']['content'];
                }
            }
        }
    }

    /**
     * Get API base URL
     */
    protected function getApiBase(): string
    {
        return $this->config['api_base'] ?? self::API_BASE;
    }
}
