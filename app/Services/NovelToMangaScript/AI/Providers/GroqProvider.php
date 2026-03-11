<?php

declare(strict_types=1);

namespace DGLab\Services\NovelToMangaScript\AI\Providers;

use DGLab\Services\NovelToMangaScript\AI\LLMResponse;
use DGLab\Services\NovelToMangaScript\AI\LLMProviderException;

/**
 * Groq LLM Provider
 * 
 * Implements the Groq API for ultra-fast inference using
 * custom LPU (Language Processing Unit) hardware.
 * 
 * Features:
 * - Extremely fast inference (sub-second for most requests)
 * - OpenAI-compatible API format
 * - Support for Llama, Mixtral, and Gemma models
 * - JSON mode support
 * 
 * @package DGLab\Services\NovelToMangaScript\AI\Providers
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
     * Default API endpoint
     */
    protected string $defaultEndpoint = 'https://api.groq.com/openai/v1/chat/completions';
    
    /**
     * Available models with their specifications
     */
    protected array $availableModels = [
        // Llama 3.3 models
        'llama-3.3-70b-versatile' => [
            'context_window' => 128000,
            'max_output' => 32768,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.00059,
            'cost_per_1k_output' => 0.00079,
        ],
        'llama-3.3-70b-specdec' => [
            'context_window' => 8192,
            'max_output' => 8192,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.00059,
            'cost_per_1k_output' => 0.00099,
        ],
        
        // Llama 3.1 models
        'llama-3.1-70b-versatile' => [
            'context_window' => 128000,
            'max_output' => 32768,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.00059,
            'cost_per_1k_output' => 0.00079,
        ],
        'llama-3.1-8b-instant' => [
            'context_window' => 128000,
            'max_output' => 8192,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.00005,
            'cost_per_1k_output' => 0.00008,
        ],
        
        // Llama 3 Vision
        'llama-3.2-90b-vision-preview' => [
            'context_window' => 128000,
            'max_output' => 8192,
            'supports_json_mode' => true,
            'supports_vision' => true,
            'cost_per_1k_input' => 0.0009,
            'cost_per_1k_output' => 0.0009,
        ],
        'llama-3.2-11b-vision-preview' => [
            'context_window' => 128000,
            'max_output' => 8192,
            'supports_json_mode' => true,
            'supports_vision' => true,
            'cost_per_1k_input' => 0.00018,
            'cost_per_1k_output' => 0.00018,
        ],
        
        // Mixtral
        'mixtral-8x7b-32768' => [
            'context_window' => 32768,
            'max_output' => 32768,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.00024,
            'cost_per_1k_output' => 0.00024,
        ],
        
        // Gemma
        'gemma2-9b-it' => [
            'context_window' => 8192,
            'max_output' => 8192,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.00020,
            'cost_per_1k_output' => 0.00020,
        ],
        
        // DeepSeek on Groq
        'deepseek-r1-distill-llama-70b' => [
            'context_window' => 128000,
            'max_output' => 16384,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.00075,
            'cost_per_1k_output' => 0.00099,
        ],
    ];
    
    /**
     * Default model for this provider
     */
    protected string $defaultModel = 'llama-3.3-70b-versatile';

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
        
        // Build messages array
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
        
        // Build request payload (OpenAI-compatible format)
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? $this->config['default_temperature'] ?? 0.7,
            'max_tokens' => min(
                $options['max_tokens'] ?? $this->config['default_max_tokens'] ?? 4096,
                $modelSpec['max_output']
            ),
            'top_p' => $options['top_p'] ?? 1.0,
            'stream' => false,
        ];
        
        // Add JSON mode if requested and supported
        if (($options['json_mode'] ?? false) && $modelSpec['supports_json_mode']) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        
        // Add stop sequences
        if (!empty($options['stop'])) {
            $payload['stop'] = $options['stop'];
        }
        
        // Add frequency and presence penalties
        if (isset($options['frequency_penalty'])) {
            $payload['frequency_penalty'] = $options['frequency_penalty'];
        }
        if (isset($options['presence_penalty'])) {
            $payload['presence_penalty'] = $options['presence_penalty'];
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
     * Make HTTP request to Groq API
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
            $errorMessage = $data['error']['message'] ?? 'Unknown error';
            throw new \RuntimeException("API error ({$httpCode}): {$errorMessage}", $httpCode);
        }
        
        return $data;
    }

    /**
     * Parse Groq API response
     */
    protected function parseResponse(array $response, string $model, float $latency): LLMResponse
    {
        $choice = $response['choices'][0] ?? null;
        
        if (!$choice) {
            throw new \RuntimeException('No response choices returned');
        }
        
        $content = $choice['message']['content'] ?? '';
        $finishReason = $choice['finish_reason'] ?? 'unknown';
        
        $usage = $response['usage'] ?? [];
        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;
        
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
                'created' => $response['created'] ?? null,
                'system_fingerprint' => $response['system_fingerprint'] ?? null,
                'groq_queue_time' => $response['x_groq']['queue_time'] ?? null,
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
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsFunctionCalling(): bool
    {
        return true; // Supported on most Groq models
    }

    /**
     * {@inheritdoc}
     */
    public function supportsVision(): bool
    {
        return true; // Some models support vision
    }

    /**
     * Get available vision-capable models
     */
    public function getVisionModels(): array
    {
        return array_keys(array_filter(
            $this->availableModels,
            fn($spec) => $spec['supports_vision']
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
}
