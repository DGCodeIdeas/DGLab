<?php

/**
 * DGLab OpenRouter Provider
 *
 * Implementation for OpenRouter API (aggregator for 100+ models).
 *
 * @package DGLab\Services\NovelToMangaScript\AI\Providers
 */

namespace DGLab\Services\NovelToMangaScript\AI\Providers;

use DGLab\Services\NovelToMangaScript\AI\LLMResponse;

/**
 * Class OpenRouterProvider
 *
 * OpenRouter API provider implementation.
 */
class OpenRouterProvider extends AbstractLLMProvider
{
    /**
     * Provider ID
     */
    private const PROVIDER_ID = 'openrouter';

    /**
     * Provider display name
     */
    private const PROVIDER_NAME = 'OpenRouter';

    /**
     * API base URL
     */
    private const API_BASE = 'https://openrouter.ai/api/v1';

    /**
     * Available models (subset - OpenRouter has 100+)
     */
    private const MODELS = [
        'openai/gpt-4o' => [
            'censorship_level' => 3,
            'cost_input' => 0.005,
            'cost_output' => 0.015,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['general'],
        ],
        'anthropic/claude-3.5-sonnet' => [
            'censorship_level' => 2,
            'cost_input' => 0.003,
            'cost_output' => 0.015,
            'context_tokens' => 200000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'creative'],
        ],
        'meta-llama/llama-3.1-405b-instruct' => [
            'censorship_level' => 0,
            'cost_input' => 0.005,
            'cost_output' => 0.005,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'creative'],
        ],
        'meta-llama/llama-3.1-70b-instruct' => [
            'censorship_level' => 0,
            'cost_input' => 0.00052,
            'cost_output' => 0.00075,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['general'],
        ],
        'mistralai/mistral-large' => [
            'censorship_level' => 1,
            'cost_input' => 0.002,
            'cost_output' => 0.006,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'multilingual'],
        ],
        'google/gemini-pro-1.5' => [
            'censorship_level' => 2,
            'cost_input' => 0.00125,
            'cost_output' => 0.005,
            'context_tokens' => 2000000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'multilingual'],
        ],
        'nousresearch/hermes-3-llama-3.1-405b' => [
            'censorship_level' => 0,
            'cost_input' => 0.005,
            'cost_output' => 0.005,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['creative', 'roleplay'],
        ],
        'deepseek/deepseek-chat' => [
            'censorship_level' => 5,
            'cost_input' => 0.00014,
            'cost_output' => 0.00028,
            'context_tokens' => 64000,
            'speed_tier' => 'fast',
            'specializations' => ['technical', 'analytical'],
        ],
        // Free models
        'meta-llama/llama-3.2-3b-instruct:free' => [
            'censorship_level' => 0,
            'cost_input' => 0,
            'cost_output' => 0,
            'context_tokens' => 8192,
            'speed_tier' => 'fast',
            'specializations' => ['general'],
            'is_free' => true,
        ],
        'mistralai/mistral-7b-instruct:free' => [
            'censorship_level' => 0,
            'cost_input' => 0,
            'cost_output' => 0,
            'context_tokens' => 32768,
            'speed_tier' => 'fast',
            'specializations' => ['general'],
            'is_free' => true,
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
     * Get category
     */
    public function getCategory(): string
    {
        return 'C';
    }

    /**
     * Get tier
     */
    public function getTier(): int
    {
        return 2;
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
        return 'meta-llama/llama-3.2-3b-instruct:free';
    }

    /**
     * Execute chat completion
     */
    public function chat(string $model, array $messages, array $options = []): LLMResponse
    {
        $this->validateMessages($messages);

        $url = self::API_BASE . '/chat/completions';

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        // OpenRouter-specific routing options
        if (!empty($options['provider_preferences'])) {
            $body['provider'] = [
                'order' => $options['provider_preferences'],
            ];
        }

        // Add transforms to allow fallback providers
        $body['route'] = 'fallback';

        $headers = [
            'Authorization' => 'Bearer ' . $this->getApiKey(),
            'Content-Type' => 'application/json',
            'HTTP-Referer' => $this->config['referer'] ?? 'https://dglab.app',
            'X-Title' => 'DGLab MangaScript',
        ];

        $start = microtime(true);
        $response = $this->httpRequest('POST', $url, $headers, $body);
        $latency = (microtime(true) - $start) * 1000;

        $llmResponse = LLMResponse::fromOpenAIFormat(
            $response,
            self::PROVIDER_ID,
            $this->currentMode === 'uncensored'
        );

        // OpenRouter provides generation cost directly
        $cost = $response['usage']['total_cost'] ?? $this->estimateCost(
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
            providerUsed: self::PROVIDER_ID,
            metadata: array_merge($llmResponse->metadata, [
                'router_model' => $model,
                'actual_provider' => $response['provider'] ?? 'unknown',
            ]),
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

        $url = self::API_BASE . '/chat/completions';

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'stream' => true,
            'route' => 'fallback',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->getApiKey(),
                'Content-Type: application/json',
                'HTTP-Referer: ' . ($this->config['referer'] ?? 'https://dglab.app'),
                'X-Title: DGLab MangaScript',
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
     * Get free models
     */
    public function getFreeModels(): array
    {
        return array_filter(self::MODELS, fn($m) => $m['is_free'] ?? false);
    }

    /**
     * Get API key (uses OPENROUTER_API_KEY env var)
     */
    protected function getApiKey(): string
    {
        $key = $this->config['api_key'] ?? null;

        if (!$key) {
            $key = getenv('OPENROUTER_API_KEY') ?: ($_ENV['OPENROUTER_API_KEY'] ?? '');
        }

        if (!$key) {
            throw new \RuntimeException('OpenRouter API key not configured');
        }

        return $key;
    }
}
