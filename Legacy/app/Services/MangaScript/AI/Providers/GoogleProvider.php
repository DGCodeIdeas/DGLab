<?php

/**
 * DGLab Google Gemini Provider
 *
 * Implementation for Google Gemini API.
 *
 * @package DGLab\Services\MangaScript\AI\Providers
 */

namespace DGLab\Services\MangaScript\AI\Providers;

use DGLab\Services\MangaScript\AI\LLMResponse;

/**
 * Class GoogleProvider
 *
 * Google Gemini API provider implementation.
 */
class GoogleProvider extends AbstractLLMProvider
{
    /**
     * Provider ID
     */
    private const PROVIDER_ID = 'google_gemini';

    /**
     * Provider display name
     */
    private const PROVIDER_NAME = 'Google Gemini';

    /**
     * API base URL
     */
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Available models
     */
    private const MODELS = [
        'gemini-1.5-pro' => [
            'censorship_level' => 2,
            'cost_input' => 0.0035,
            'cost_output' => 0.0105,
            'context_tokens' => 2000000,
            'speed_tier' => 'fast',
            'specializations' => ['general', 'multilingual'],
            'configurable_safety' => true,
        ],
        'gemini-1.5-flash' => [
            'censorship_level' => 2,
            'cost_input' => 0.00035,
            'cost_output' => 0.00105,
            'context_tokens' => 1000000,
            'speed_tier' => 'ultra',
            'specializations' => ['general'],
            'configurable_safety' => true,
        ],
        'gemini-1.0-pro' => [
            'censorship_level' => 2,
            'cost_input' => 0.0005,
            'cost_output' => 0.0015,
            'context_tokens' => 32000,
            'speed_tier' => 'fast',
            'specializations' => ['general'],
        ],
        'gemini-2.0-flash' => [
            'censorship_level' => 2,
            'cost_input' => 0.0001,
            'cost_output' => 0.0003,
            'context_tokens' => 1000000,
            'speed_tier' => 'ultra',
            'specializations' => ['general', 'multilingual'],
            'configurable_safety' => true,
        ],
    ];

    /**
     * Safety settings by mode
     */
    private const SAFETY_SETTINGS = [
        'censored' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ],
        'uncensored' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
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
        return 'gemini-1.5-flash';
    }

    /**
     * Execute chat completion
     */
    public function chat(string $model, array $messages, array $options = []): LLMResponse
    {
        $this->validateMessages($messages);

        $url = self::API_BASE . "/models/{$model}:generateContent?key=" . $this->getApiKey();

        // Convert messages to Google format
        $contents = $this->formatMessages($messages);

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 4096,
            ],
        ];

        // Apply safety settings based on mode
        $safetyMode = $this->currentMode === 'uncensored' ? 'uncensored' : 'censored';
        $body['safetySettings'] = self::SAFETY_SETTINGS[$safetyMode];

        // Add JSON mode if requested
        if ($options['json_mode'] ?? false) {
            $body['generationConfig']['responseMimeType'] = 'application/json';
        }

        // Add stop sequences if provided
        if (!empty($options['stop'])) {
            $body['generationConfig']['stopSequences'] = $options['stop'];
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $start = microtime(true);
        $response = $this->httpRequest('POST', $url, $headers, $body);
        $latency = (microtime(true) - $start) * 1000;

        $llmResponse = LLMResponse::fromGoogleFormat(
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
            modelUsed: $model,
            providerUsed: self::PROVIDER_ID,
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

        $url = self::API_BASE . "/models/{$model}:streamGenerateContent?key=" . $this->getApiKey();

        $contents = $this->formatMessages($messages);

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 4096,
            ],
        ];

        $safetyMode = $this->currentMode === 'uncensored' ? 'uncensored' : 'censored';
        $body['safetySettings'] = self::SAFETY_SETTINGS[$safetyMode];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
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

        // Parse JSON array response
        $responses = json_decode($buffer, true);
        if (is_array($responses)) {
            foreach ($responses as $response) {
                if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                    yield $response['candidates'][0]['content']['parts'][0]['text'];
                }
            }
        }
    }

    /**
     * Format messages for Google API
     */
    protected function formatMessages(array $messages): array
    {
        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemInstruction = $message['content'];
                continue;
            }

            $role = $message['role'] === 'assistant' ? 'model' : 'user';

            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $message['content']]],
            ];
        }

        // Prepend system instruction to first user message if exists
        if ($systemInstruction && !empty($contents)) {
            $contents[0]['parts'][0]['text'] = "Instructions: {$systemInstruction}\n\n" . $contents[0]['parts'][0]['text'];
        }

        return $contents;
    }

    /**
     * Get API key (uses GOOGLE_API_KEY env var)
     */
    protected function getApiKey(): string
    {
        $key = $this->config['api_key'] ?? null;

        if (!$key) {
            $key = getenv('GOOGLE_API_KEY') ?: ($_ENV['GOOGLE_API_KEY'] ?? '');
        }

        if (!$key) {
            throw new \RuntimeException('Google API key not configured');
        }

        return $key;
    }
}
