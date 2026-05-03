<?php

declare(strict_types=1);

namespace DGLab\Services\MangaScript\AI\Providers;

use DGLab\Services\MangaScript\AI\LLMResponse;
use DGLab\Services\MangaScript\AI\LLMProviderException;

/**
 * Cohere LLM Provider
 *
 * Implements the Cohere API for enterprise-grade language models
 * with strong RAG and embedding capabilities.
 *
 * Features:
 * - Command models for generation
 * - Strong multilingual support
 * - Built-in RAG with connectors
 * - Document grounding for factual responses
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
    protected string $defaultEndpoint = 'https://api.cohere.ai/v2/chat';

    /**
     * Available models with their specifications
     */
    protected array $availableModels = [
        // Command R+ models
        'command-r-plus-08-2024' => [
            'context_window' => 128000,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'supports_tools' => true,
            'supports_rag' => true,
            'cost_per_1k_input' => 0.0025,
            'cost_per_1k_output' => 0.01,
        ],
        'command-r-plus' => [
            'context_window' => 128000,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'supports_tools' => true,
            'supports_rag' => true,
            'cost_per_1k_input' => 0.0025,
            'cost_per_1k_output' => 0.01,
        ],

        // Command R models
        'command-r-08-2024' => [
            'context_window' => 128000,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'supports_tools' => true,
            'supports_rag' => true,
            'cost_per_1k_input' => 0.00015,
            'cost_per_1k_output' => 0.0006,
        ],
        'command-r' => [
            'context_window' => 128000,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'supports_tools' => true,
            'supports_rag' => true,
            'cost_per_1k_input' => 0.00015,
            'cost_per_1k_output' => 0.0006,
        ],

        // Command models (legacy)
        'command' => [
            'context_window' => 4096,
            'max_output' => 4096,
            'supports_json_mode' => false,
            'supports_tools' => false,
            'supports_rag' => false,
            'cost_per_1k_input' => 0.001,
            'cost_per_1k_output' => 0.002,
        ],
        'command-light' => [
            'context_window' => 4096,
            'max_output' => 4096,
            'supports_json_mode' => false,
            'supports_tools' => false,
            'supports_rag' => false,
            'cost_per_1k_input' => 0.0003,
            'cost_per_1k_output' => 0.0006,
        ],
    ];

    /**
     * Default model for this provider
     */
    protected string $defaultModel = 'command-r-plus';

    /**
     * {@inheritdoc}
     */
    public function send(
        string $prompt,
        ?string $systemPrompt = null,
        array $options = []
    ): LLMResponse {
        $model = $options['model'] ?? $this->defaultModel;
        $this->validateModel($model);

        $modelSpec = $this->availableModels[$model];

        // Build messages array for v2 API
        $messages = [];

        if ($systemPrompt !== null) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? $this->config['default_temperature'] ?? 0.7,
            'max_tokens' => min(
                $options['max_tokens'] ?? $this->config['default_max_tokens'] ?? 4096,
                $modelSpec['max_output']
            ),
            'stream' => false,
        ];

        // Add response format for JSON mode
        if (($options['json_mode'] ?? false) && $modelSpec['supports_json_mode']) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        // Add safety mode
        if (isset($options['safety_mode'])) {
            $payload['safety_mode'] = $options['safety_mode']; // CONTEXTUAL, STRICT, NONE
        }

        // Add stop sequences
        if (!empty($options['stop'])) {
            $payload['stop_sequences'] = $options['stop'];
        }

        // Add document grounding if provided
        if (!empty($options['documents'])) {
            $payload['documents'] = $options['documents'];
        }

        // Add citation quality
        if (isset($options['citation_quality'])) {
            $payload['citation_quality'] = $options['citation_quality'];
        }

        $startTime = microtime(true);

        try {
            $response = $this->makeRequest($payload);
            $latency = (microtime(true) - $startTime) * 1000;

            return $this->parseResponse($response, $model, $latency);
        } catch (\Exception $e) {
            throw LLMProviderException::requestFailed(
                $this->providerId,
                $model,
                $e->getMessage(),
                $e->getCode()
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sendWithHistory(
        array $messages,
        ?string $systemPrompt = null,
        array $options = []
    ): LLMResponse {
        $model = $options['model'] ?? $this->defaultModel;
        $this->validateModel($model);

        $modelSpec = $this->availableModels[$model];

        // Build messages array
        $formattedMessages = [];

        if ($systemPrompt !== null) {
            $formattedMessages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => $formattedMessages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => min(
                $options['max_tokens'] ?? 4096,
                $modelSpec['max_output']
            ),
            'stream' => false,
        ];

        if (($options['json_mode'] ?? false) && $modelSpec['supports_json_mode']) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $startTime = microtime(true);

        try {
            $response = $this->makeRequest($payload);
            $latency = (microtime(true) - $startTime) * 1000;

            return $this->parseResponse($response, $model, $latency);
        } catch (\Exception $e) {
            throw LLMProviderException::requestFailed(
                $this->providerId,
                $model,
                $e->getMessage(),
                $e->getCode()
            );
        }
    }

    /**
     * Make HTTP request to Cohere API
     */
    protected function makeRequest(array $payload): array
    {
        $endpoint = $this->config['endpoint'] ?? $this->defaultEndpoint;

        $ch = curl_init($endpoint);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'X-Client-Name: DGLab-NovelToMangaScript',
            ],
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 120,
            CURLOPT_CONNECTTIMEOUT => $this->config['connect_timeout'] ?? 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("cURL error: {$error}");
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMessage = $data['message'] ?? 'Unknown error';
            throw new \RuntimeException("API error ({$httpCode}): {$errorMessage}", $httpCode);
        }

        return $data;
    }

    /**
     * Parse Cohere API response (v2 format)
     */
    protected function parseResponse(array $response, string $model, float $latency): LLMResponse
    {
        $content = '';

        // v2 API returns message with content array
        if (isset($response['message']['content'])) {
            foreach ($response['message']['content'] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }
        }

        $finishReason = $response['finish_reason'] ?? 'unknown';

        $usage = $response['usage'] ?? [];
        $inputTokens = $usage['billed_units']['input_tokens'] ?? $usage['tokens']['input_tokens'] ?? 0;
        $outputTokens = $usage['billed_units']['output_tokens'] ?? $usage['tokens']['output_tokens'] ?? 0;

        // Calculate cost
        $modelSpec = $this->availableModels[$model];
        $cost = (($inputTokens / 1000) * $modelSpec['cost_per_1k_input']) +
                (($outputTokens / 1000) * $modelSpec['cost_per_1k_output']);

        return new LLMResponse(
            content: $content,
            provider: $this->providerId,
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            totalTokens: $inputTokens + $outputTokens,
            cost: $cost,
            latencyMs: $latency,
            finishReason: $finishReason,
            metadata: [
                'response_id' => $response['id'] ?? null,
                'citations' => $response['citations'] ?? [],
                'search_queries' => $response['search_queries'] ?? [],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableModels(): array
    {
        return array_keys($this->availableModels);
    }

    /**
     * {@inheritdoc}
     */
    public function getModelCapabilities(string $model): array
    {
        $this->validateModel($model);
        return $this->availableModels[$model];
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
        return true; // Command R models support JSON mode
    }

    /**
     * {@inheritdoc}
     */
    public function supportsFunctionCalling(): bool
    {
        return true; // Command R models support tools
    }

    /**
     * {@inheritdoc}
     */
    public function supportsVision(): bool
    {
        return false; // Cohere doesn't have vision models yet
    }

    /**
     * Check if provider supports RAG
     */
    public function supportsRag(): bool
    {
        return true;
    }

    /**
     * Get models that support RAG
     */
    public function getRagModels(): array
    {
        return array_keys(array_filter(
            $this->availableModels,
            fn($spec) => $spec['supports_rag'] ?? false
        ));
    }

    /**
     * Validate model exists
     */
    protected function validateModel(string $model): void
    {
        if (!isset($this->availableModels[$model])) {
            throw LLMProviderException::invalidModel(
                $this->providerId,
                $model,
                $this->getAvailableModels()
            );
        }
    }

    /**
     * Get provider identifier
     */
    public function getId(): string
    {
        return 'cohere';
    }

    /**
     * Get display name
     */
    public function getName(): string
    {
        return 'Cohere';
    }

    /**
     * Get available models
     */
    public function getModels(): array
    {
        return ->availableModels;
    }

    /**
     * Get default model
     */
    protected function getDefaultModel(): string
    {
        return 'command-r-plus';
    }

    /**
     * Execute a chat completion
     */
    public function chat(string $model, array $messages, array $options = []): \DGLab\Services\MangaScript\AI\LLMResponse
    {
        return $this->sendWithHistory($messages, null, array_merge($options, ['model' => $model]));
    }

    /**
     * Execute streaming chat completion
     */
    public function chatStream(string $model, array $messages, array $options = []): \Generator
    {
        if (false) {
            yield '';
        }
        throw new \RuntimeException('Streaming not implemented for ' . $this->getName());
    }
}
