<?php

/**
 * DGLab Mistral AI Provider
 *
 * Implementation for Mistral AI API.
 *
 * @package DGLab\Services\NovelToMangaScript\AI\Providers
 */

namespace DGLab\Services\NovelToMangaScript\AI\Providers;

use DGLab\Services\NovelToMangaScript\AI\LLMResponse;

/**
 * Class MistralProvider
 *
 * Mistral AI API provider implementation.
 */
class MistralProvider extends AbstractLLMProvider
{
    /**
     * Provider ID
     */
    private const PROVIDER_ID = 'mistral';

    /**
     * Provider display name
     */
    private const PROVIDER_NAME = 'Mistral AI';

    /**
     * API base URL
     */
    private const API_BASE = 'https://api.mistral.ai/v1';

    /**
     * Available models
     */
    private const MODELS = [
        'mistral-large-latest' => [
            'censorship_level' => 1,
            'cost_input' => 0.002,
            'cost_output' => 0.006,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'multilingual'],
        ],
        'mistral-medium-latest' => [
            'censorship_level' => 1,
            'cost_input' => 0.0007,
            'cost_output' => 0.002,
            'context_tokens' => 32000,
            'speed_tier' => 'fast',
            'specializations' => ['general'],
        ],
        'mistral-small-latest' => [
            'censorship_level' => 1,
            'cost_input' => 0.0002,
            'cost_output' => 0.0006,
            'context_tokens' => 32000,
            'speed_tier' => 'ultra',
            'specializations' => ['general'],
        ],
        'codestral-latest' => [
            'censorship_level' => 1,
            'cost_input' => 0.001,
            'cost_output' => 0.003,
            'context_tokens' => 32000,
            'speed_tier' => 'fast',
            'specializations' => ['technical'],
        ],
        'open-mixtral-8x22b' => [
            'censorship_level' => 0,
            'cost_input' => 0.002,
            'cost_output' => 0.006,
            'context_tokens' => 64000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'creative'],
        ],
        'open-mistral-nemo' => [
            'censorship_level' => 0,
            'cost_input' => 0.0003,
            'cost_output' => 0.0003,
            'context_tokens' => 128000,
            'speed_tier' => 'ultra',
            'specializations' => ['multilingual'],
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
        return 'B';
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
        return 'open-mistral-nemo';
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

        // Add JSON mode if requested
        if ($options['json_mode'] ?? false) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        // Mistral supports safe_prompt for additional safety
        if ($this->currentMode === 'censored') {
            $body['safe_prompt'] = true;
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

        $url = self::API_BASE . '/chat/completions';

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
     * Get API key (uses MISTRAL_API_KEY env var)
     */
    protected function getApiKey(): string
    {
        $key = $this->config['api_key'] ?? null;

        if (!$key) {
            $key = getenv('MISTRAL_API_KEY') ?: ($_ENV['MISTRAL_API_KEY'] ?? '');
        }

        if (!$key) {
            throw new \RuntimeException('Mistral API key not configured');
        }

        return $key;
    }
}
