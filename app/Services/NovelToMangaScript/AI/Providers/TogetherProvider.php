<?php

/**
 * DGLab Together AI Provider
 *
 * Implementation for Together AI API (open source models hosting).
 *
 * @package DGLab\Services\NovelToMangaScript\AI\Providers
 */

namespace DGLab\Services\NovelToMangaScript\AI\Providers;

use DGLab\Services\NovelToMangaScript\AI\LLMResponse;

/**
 * Class TogetherProvider
 *
 * Together AI API provider implementation.
 */
class TogetherProvider extends AbstractLLMProvider
{
    /**
     * Provider ID
     */
    private const PROVIDER_ID = 'together';

    /**
     * Provider display name
     */
    private const PROVIDER_NAME = 'Together AI';

    /**
     * API base URL
     */
    private const API_BASE = 'https://api.together.xyz/v1';

    /**
     * Available models
     */
    private const MODELS = [
        'meta-llama/Meta-Llama-3.1-405B-Instruct-Turbo' => [
            'censorship_level' => 0,
            'cost_input' => 0.005,
            'cost_output' => 0.005,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'creative'],
            'openness' => 'weights_available',
        ],
        'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo' => [
            'censorship_level' => 0,
            'cost_input' => 0.00088,
            'cost_output' => 0.00088,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'creative'],
        ],
        'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo' => [
            'censorship_level' => 0,
            'cost_input' => 0.00018,
            'cost_output' => 0.00018,
            'context_tokens' => 128000,
            'speed_tier' => 'ultra',
            'specializations' => ['general'],
        ],
        'NousResearch/Nous-Hermes-2-Yi-34B' => [
            'censorship_level' => 0,
            'cost_input' => 0.0008,
            'cost_output' => 0.0008,
            'context_tokens' => 32000,
            'speed_tier' => 'fast',
            'specializations' => ['creative', 'roleplay'],
        ],
        'NousResearch/Hermes-3-Llama-3.1-405B' => [
            'censorship_level' => 0,
            'cost_input' => 0.005,
            'cost_output' => 0.005,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['creative', 'analytical'],
        ],
        'mistralai/Mixtral-8x22B-Instruct-v0.1' => [
            'censorship_level' => 0,
            'cost_input' => 0.0012,
            'cost_output' => 0.0012,
            'context_tokens' => 65536,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'multilingual'],
        ],
        'Qwen/Qwen2-72B-Instruct' => [
            'censorship_level' => 1,
            'cost_input' => 0.0009,
            'cost_output' => 0.0009,
            'context_tokens' => 32768,
            'speed_tier' => 'fast',
            'specializations' => ['multilingual', 'technical'],
        ],
        'deepseek-ai/deepseek-llm-67b-chat' => [
            'censorship_level' => 1,
            'cost_input' => 0.0009,
            'cost_output' => 0.0009,
            'context_tokens' => 32000,
            'speed_tier' => 'fast',
            'specializations' => ['technical', 'analytical'],
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
        return 'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo';
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

        // Add repetition penalty for creative models
        if (in_array('creative', self::MODELS[$model]['specializations'] ?? [])) {
            $body['repetition_penalty'] = $options['repetition_penalty'] ?? 1.1;
        }

        // Add stop sequences
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
     * Get API key (uses TOGETHER_API_KEY env var)
     */
    protected function getApiKey(): string
    {
        $key = $this->config['api_key'] ?? null;

        if (!$key) {
            $key = getenv('TOGETHER_API_KEY') ?: ($_ENV['TOGETHER_API_KEY'] ?? '');
        }

        if (!$key) {
            throw new \RuntimeException('Together API key not configured');
        }

        return $key;
    }
}
