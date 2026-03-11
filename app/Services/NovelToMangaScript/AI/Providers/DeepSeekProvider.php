<?php

/**
 * DGLab DeepSeek Provider
 *
 * Implementation for DeepSeek API (Chinese regional provider).
 *
 * @package DGLab\Services\NovelToMangaScript\AI\Providers
 */

namespace DGLab\Services\NovelToMangaScript\AI\Providers;

use DGLab\Services\NovelToMangaScript\AI\LLMResponse;

/**
 * Class DeepSeekProvider
 *
 * DeepSeek API provider implementation.
 */
class DeepSeekProvider extends AbstractLLMProvider
{
    /**
     * Provider ID
     */
    private const PROVIDER_ID = 'deepseek';

    /**
     * Provider display name
     */
    private const PROVIDER_NAME = 'DeepSeek';

    /**
     * API base URL
     */
    private const API_BASE = 'https://api.deepseek.com';

    /**
     * Available models
     */
    private const MODELS = [
        'deepseek-chat' => [
            'censorship_level' => 5, // Regulatory (CN law)
            'cost_input' => 0.00014,
            'cost_output' => 0.00028,
            'context_tokens' => 64000,
            'speed_tier' => 'fast',
            'specializations' => ['technical', 'analytical'],
        ],
        'deepseek-coder' => [
            'censorship_level' => 5,
            'cost_input' => 0.00014,
            'cost_output' => 0.00028,
            'context_tokens' => 64000,
            'speed_tier' => 'fast',
            'specializations' => ['technical'],
        ],
        'deepseek-reasoner' => [
            'censorship_level' => 5,
            'cost_input' => 0.00055,
            'cost_output' => 0.00220,
            'context_tokens' => 64000,
            'speed_tier' => 'batch',
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
     * Get category
     */
    public function getCategory(): string
    {
        return 'D';
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
        return 'deepseek-chat';
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
            false // DeepSeek has regulatory censorship
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
            metadata: array_merge($llmResponse->metadata, [
                'region' => 'CN',
                'compliance_note' => 'Subject to PRC cybersecurity law',
            ]),
            isUncensored: false,
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
     * Get API key (uses DEEPSEEK_API_KEY env var)
     */
    protected function getApiKey(): string
    {
        $key = $this->config['api_key'] ?? null;

        if (!$key) {
            $key = getenv('DEEPSEEK_API_KEY') ?: ($_ENV['DEEPSEEK_API_KEY'] ?? '');
        }

        if (!$key) {
            throw new \RuntimeException('DeepSeek API key not configured');
        }

        return $key;
    }
}
