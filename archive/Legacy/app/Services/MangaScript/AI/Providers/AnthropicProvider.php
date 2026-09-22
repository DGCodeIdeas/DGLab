<?php

/**
 * DGLab Anthropic Provider
 *
 * Implementation for Anthropic API (Claude 3.5, 3 Opus models).
 *
 * @package DGLab\Services\MangaScript\AI\Providers
 */

namespace DGLab\Services\MangaScript\AI\Providers;

use DGLab\Services\MangaScript\AI\LLMResponse;

/**
 * Class AnthropicProvider
 *
 * Anthropic API provider implementation.
 */
class AnthropicProvider extends AbstractLLMProvider
{
    /**
     * Provider ID
     */
    private const PROVIDER_ID = 'anthropic';

    /**
     * Provider display name
     */
    private const PROVIDER_NAME = 'Anthropic';

    /**
     * API base URL
     */
    private const API_BASE = 'https://api.anthropic.com';

    /**
     * API version
     */
    private const API_VERSION = '2023-06-01';

    /**
     * Available models
     */
    private const MODELS = [
        'claude-3-5-sonnet-20241022' => [
            'censorship_level' => 2,
            'cost_input' => 0.003,
            'cost_output' => 0.015,
            'context_tokens' => 200000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'analytical', 'creative'],
        ],
        'claude-3-opus-20240229' => [
            'censorship_level' => 2,
            'cost_input' => 0.015,
            'cost_output' => 0.075,
            'context_tokens' => 200000,
            'speed_tier' => 'standard',
            'specializations' => ['analytical', 'technical', 'creative'],
        ],
        'claude-3-5-haiku-20241022' => [
            'censorship_level' => 2,
            'cost_input' => 0.001,
            'cost_output' => 0.005,
            'context_tokens' => 200000,
            'speed_tier' => 'ultra',
            'specializations' => ['general'],
        ],
        'claude-3-sonnet-20240229' => [
            'censorship_level' => 2,
            'cost_input' => 0.003,
            'cost_output' => 0.015,
            'context_tokens' => 200000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'creative'],
        ],
        'claude-3-haiku-20240307' => [
            'censorship_level' => 2,
            'cost_input' => 0.00025,
            'cost_output' => 0.00125,
            'context_tokens' => 200000,
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
        return 'claude-3-5-haiku-20241022';
    }

    /**
     * Execute chat completion
     */
    public function chat(string $model, array $messages, array $options = []): LLMResponse
    {
        $this->validateMessages($messages);

        $url = self::API_BASE . '/v1/messages';

        // Extract system message if present
        $systemMessage = null;
        $chatMessages = [];
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message['content'];
            } else {
                $chatMessages[] = [
                    'role' => $message['role'],
                    'content' => $message['content'],
                ];
            }
        }

        $body = [
            'model' => $model,
            'messages' => $chatMessages,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        if ($systemMessage) {
            $body['system'] = $systemMessage;
        }

        // Add temperature (Claude defaults to 1.0)
        if (isset($options['temperature'])) {
            $body['temperature'] = $options['temperature'];
        }

        // Add stop sequences if provided
        if (!empty($options['stop'])) {
            $body['stop_sequences'] = $options['stop'];
        }

        $headers = [
            'x-api-key' => $this->getApiKey(),
            'Content-Type' => 'application/json',
            'anthropic-version' => self::API_VERSION,
        ];

        $start = microtime(true);
        $response = $this->httpRequest('POST', $url, $headers, $body);
        $latency = (microtime(true) - $start) * 1000;

        $llmResponse = LLMResponse::fromAnthropicFormat(
            $response,
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

        $url = self::API_BASE . '/v1/messages';

        // Extract system message
        $systemMessage = null;
        $chatMessages = [];
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message['content'];
            } else {
                $chatMessages[] = [
                    'role' => $message['role'],
                    'content' => $message['content'],
                ];
            }
        }

        $body = [
            'model' => $model,
            'messages' => $chatMessages,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'stream' => true,
        ];

        if ($systemMessage) {
            $body['system'] = $systemMessage;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $this->getApiKey(),
                'Content-Type: application/json',
                'anthropic-version: ' . self::API_VERSION,
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
                $data = json_decode($json, true);

                if ($data && $data['type'] === 'content_block_delta') {
                    if (isset($data['delta']['text'])) {
                        yield $data['delta']['text'];
                    }
                }
            }
        }
    }

    /**
     * Get API key (uses ANTHROPIC_API_KEY env var)
     */
    protected function getApiKey(): string
    {
        $key = $this->config['api_key'] ?? null;

        if (!$key) {
            $key = getenv('ANTHROPIC_API_KEY') ?: ($_ENV['ANTHROPIC_API_KEY'] ?? '');
        }

        if (!$key) {
            throw new \RuntimeException('Anthropic API key not configured');
        }

        return $key;
    }
}
