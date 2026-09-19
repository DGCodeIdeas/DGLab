<?php

/**
 * DGLab Ollama Provider
 *
 * Implementation for local Ollama API.
 *
 * @package DGLab\Services\MangaScript\AI\Providers
 */

namespace DGLab\Services\MangaScript\AI\Providers;

use DGLab\Services\MangaScript\AI\LLMResponse;
use DGLab\Services\MangaScript\AI\LLMProviderException;

/**
 * Class OllamaProvider
 *
 * Local Ollama API provider implementation.
 */
class OllamaProvider extends AbstractLLMProvider
{
    /**
     * Provider ID
     */
    private const PROVIDER_ID = 'ollama';

    /**
     * Provider display name
     */
    private const PROVIDER_NAME = 'Ollama';

    /**
     * Default API base URL
     */
    private const DEFAULT_API_BASE = 'http://localhost:11434';

    /**
     * Common models (actual availability depends on user installation)
     */
    private const MODELS = [
        'llama3.1:405b' => [
            'censorship_level' => 0,
            'cost_input' => 0,
            'cost_output' => 0,
            'context_tokens' => 128000,
            'speed_tier' => 'standard',
            'specializations' => ['general', 'creative'],
            'hardware_required' => ['gpu' => '80GB+ VRAM'],
        ],
        'llama3.1:70b' => [
            'censorship_level' => 0,
            'cost_input' => 0,
            'cost_output' => 0,
            'context_tokens' => 128000,
            'speed_tier' => 'standard',
            'specializations' => ['general', 'creative'],
            'hardware_required' => ['gpu' => '48GB+ VRAM'],
        ],
        'llama3.1:8b' => [
            'censorship_level' => 0,
            'cost_input' => 0,
            'cost_output' => 0,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['general'],
            'hardware_required' => ['gpu' => '8GB+ VRAM or CPU'],
        ],
        'mistral-nemo:12b' => [
            'censorship_level' => 0,
            'cost_input' => 0,
            'cost_output' => 0,
            'context_tokens' => 128000,
            'speed_tier' => 'fast',
            'specializations' => ['multilingual'],
        ],
        'qwen2.5:72b' => [
            'censorship_level' => 0,
            'cost_input' => 0,
            'cost_output' => 0,
            'context_tokens' => 128000,
            'speed_tier' => 'standard',
            'specializations' => ['multilingual', 'technical'],
        ],
        'qwen2.5:7b' => [
            'censorship_level' => 0,
            'cost_input' => 0,
            'cost_output' => 0,
            'context_tokens' => 128000,
            'speed_tier' => 'ultra',
            'specializations' => ['general'],
        ],
        'dolphin-llama3:70b' => [
            'censorship_level' => 0,
            'cost_input' => 0,
            'cost_output' => 0,
            'context_tokens' => 8192,
            'speed_tier' => 'standard',
            'specializations' => ['creative', 'roleplay'],
            'note' => 'Uncensored fine-tune',
        ],
        'hermes3:70b' => [
            'censorship_level' => 0,
            'cost_input' => 0,
            'cost_output' => 0,
            'context_tokens' => 128000,
            'speed_tier' => 'standard',
            'specializations' => ['creative', 'analytical'],
        ],
        'codellama:34b' => [
            'censorship_level' => 0,
            'cost_input' => 0,
            'cost_output' => 0,
            'context_tokens' => 16384,
            'speed_tier' => 'standard',
            'specializations' => ['technical'],
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
        return 'E';
    }

    /**
     * Get tier
     */
    public function getTier(): int
    {
        return 3;
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
        return 'llama3.1:8b';
    }

    /**
     * Execute chat completion
     */
    public function chat(string $model, array $messages, array $options = []): LLMResponse
    {
        $this->validateMessages($messages);

        $url = $this->getApiBase() . '/api/chat';

        $body = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'num_predict' => $options['max_tokens'] ?? 4096,
            ],
        ];

        // Add JSON mode if requested
        if ($options['json_mode'] ?? false) {
            $body['format'] = 'json';
        }

        // Add stop sequences
        if (!empty($options['stop'])) {
            $body['options']['stop'] = $options['stop'];
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $start = microtime(true);

        try {
            $response = $this->httpRequest('POST', $url, $headers, $body);
        } catch (LLMProviderException $e) {
            // Check if Ollama is not running
            if ($e->errorType === LLMProviderException::TYPE_NETWORK) {
                throw new LLMProviderException(
                    'Ollama is not running. Start it with: ollama serve',
                    LLMProviderException::TYPE_NETWORK,
                    self::PROVIDER_ID,
                    $model
                );
            }
            throw $e;
        }

        $latency = (microtime(true) - $start) * 1000;

        // Ollama response format
        $content = $response['message']['content'] ?? '';
        $inputTokens = $response['prompt_eval_count'] ?? 0;
        $outputTokens = $response['eval_count'] ?? 0;

        return new LLMResponse(
            content: $content,
            finishReason: $response['done'] ? 'stop' : 'length',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            modelUsed: $model,
            providerUsed: self::PROVIDER_ID,
            metadata: [
                'total_duration' => $response['total_duration'] ?? 0,
                'load_duration' => $response['load_duration'] ?? 0,
                'eval_duration' => $response['eval_duration'] ?? 0,
            ],
            isUncensored: true, // Local models are always uncensored
            latencyMs: $latency,
            costUsd: 0 // Local models are free
        );
    }

    /**
     * Execute streaming chat completion
     */
    public function chatStream(string $model, array $messages, array $options = []): \Generator
    {
        $this->validateMessages($messages);

        $url = $this->getApiBase() . '/api/chat';

        $body = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'options' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'num_predict' => $options['max_tokens'] ?? 4096,
            ],
        ];

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

        // Parse NDJSON stream
        $lines = explode("\n", $buffer);
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $data = json_decode($line, true);
            if ($data && isset($data['message']['content'])) {
                yield $data['message']['content'];
            }

            if ($data['done'] ?? false) {
                break;
            }
        }
    }

    /**
     * Test connection and list available models
     */
    public function testConnection(): array
    {
        $start = microtime(true);

        try {
            $url = $this->getApiBase() . '/api/tags';
            $response = $this->executeHttpRequest('GET', $url, []);

            $latency = (microtime(true) - $start) * 1000;

            $models = array_map(
                fn($m) => $m['name'],
                $response['models'] ?? []
            );

            return [
                'success' => true,
                'latency_ms' => round($latency, 2),
                'available_models' => $models,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'latency_ms' => (microtime(true) - $start) * 1000,
                'error' => 'Ollama not running: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get installed models from Ollama
     */
    public function getInstalledModels(): array
    {
        try {
            $url = $this->getApiBase() . '/api/tags';
            $response = $this->executeHttpRequest('GET', $url, []);

            return array_map(
                fn($m) => [
                    'name' => $m['name'],
                    'size' => $m['size'] ?? 0,
                    'modified' => $m['modified_at'] ?? null,
                ],
                $response['models'] ?? []
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get API base URL
     */
    protected function getApiBase(): string
    {
        return $this->config['api_base']
            ?? getenv('OLLAMA_HOST')
            ?: ($_ENV['OLLAMA_HOST'] ?? self::DEFAULT_API_BASE);
    }

    /**
     * No API key needed for local Ollama
     */
    protected function getApiKey(): string
    {
        return '';
    }
}
