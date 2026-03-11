<?php

/**
 * DGLab Abstract LLM Provider
 *
 * Base class for all LLM providers with common functionality.
 *
 * @package DGLab\Services\NovelToMangaScript\AI\Providers
 */

namespace DGLab\Services\NovelToMangaScript\AI\Providers;

use DGLab\Services\NovelToMangaScript\AI\Contracts\LLMProviderInterface;
use DGLab\Services\NovelToMangaScript\AI\LLMProviderException;
use DGLab\Services\NovelToMangaScript\AI\LLMResponse;

/**
 * Class AbstractLLMProvider
 *
 * Abstract base class providing common provider functionality.
 */
abstract class AbstractLLMProvider implements LLMProviderInterface
{
    /**
     * Provider configuration
     */
    protected array $config;

    /**
     * Current operating mode
     */
    protected string $currentMode = 'censored';

    /**
     * HTTP client timeout
     */
    protected int $timeout = 60;

    /**
     * Maximum retry attempts
     */
    protected int $maxRetries = 3;

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->timeout = $config['timeout'] ?? 60;
        $this->maxRetries = $config['max_retries'] ?? 3;
    }

    /**
     * Get category (default A)
     */
    public function getCategory(): string
    {
        return $this->config['category'] ?? 'A';
    }

    /**
     * Get tier (default 1)
     */
    public function getTier(): int
    {
        return $this->config['tier'] ?? 1;
    }

    /**
     * Check if model exists
     */
    public function hasModel(string $modelId): bool
    {
        return isset($this->getModels()[$modelId]);
    }

    /**
     * Get model configuration
     */
    public function getModelConfig(string $modelId): ?array
    {
        return $this->getModels()[$modelId] ?? null;
    }

    /**
     * Set mode
     */
    public function setMode(string $mode): self
    {
        if (!in_array($mode, ['censored', 'uncensored', 'auto'])) {
            throw new \InvalidArgumentException("Invalid mode: {$mode}");
        }
        $this->currentMode = $mode;
        return $this;
    }

    /**
     * Get current mode
     */
    public function getMode(): string
    {
        return $this->currentMode;
    }

    /**
     * Test connection
     */
    public function testConnection(): array
    {
        $start = microtime(true);

        try {
            $response = $this->chat(
                $this->getDefaultModel(),
                [['role' => 'user', 'content' => 'Say "OK"']],
                ['max_tokens' => 5]
            );

            $latency = (microtime(true) - $start) * 1000;

            return [
                'success' => true,
                'latency_ms' => round($latency, 2),
                'model' => $response->modelUsed,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'latency_ms' => (microtime(true) - $start) * 1000,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get status (default implementation)
     */
    public function getStatus(): array
    {
        return [
            'available' => true,
            'uptime_30d' => 0.99,
        ];
    }

    /**
     * Get pricing
     */
    public function getPricing(): array
    {
        $pricing = [];
        foreach ($this->getModels() as $modelId => $config) {
            $pricing[$modelId] = [
                'input' => $config['cost_input'] ?? 0,
                'output' => $config['cost_output'] ?? 0,
            ];
        }
        return $pricing;
    }

    /**
     * Estimate cost
     */
    public function estimateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $config = $this->getModelConfig($model);
        if (!$config) {
            return 0;
        }

        $inputCost = ($inputTokens / 1000) * ($config['cost_input'] ?? 0);
        $outputCost = ($outputTokens / 1000) * ($config['cost_output'] ?? 0);

        return $inputCost + $outputCost;
    }

    /**
     * Get provider metadata
     */
    public function getMetadata(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'category' => $this->getCategory(),
            'tier' => $this->getTier(),
            'supports_streaming' => $this->supportsStreaming(),
            'supports_json_mode' => $this->supportsJsonMode(),
            'models' => array_keys($this->getModels()),
            'pricing' => $this->getPricing(),
        ];
    }

    /**
     * Get default model for this provider
     */
    abstract protected function getDefaultModel(): string;

    /**
     * Make HTTP request with retry logic
     */
    protected function httpRequest(
        string $method,
        string $url,
        array $headers = [],
        ?array $body = null
    ): array {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                return $this->executeHttpRequest($method, $url, $headers, $body);
            } catch (LLMProviderException $e) {
                $lastException = $e;

                if (!$e->isRetryable) {
                    throw $e;
                }

                if ($e->retryAfterSeconds && $attempt < $this->maxRetries) {
                    sleep(min($e->retryAfterSeconds, 10));
                }
            }
        }

        throw $lastException ?? new LLMProviderException(
            'Max retries exceeded',
            LLMProviderException::TYPE_NETWORK,
            $this->getId()
        );
    }

    /**
     * Execute single HTTP request
     */
    protected function executeHttpRequest(
        string $method,
        string $url,
        array $headers = [],
        ?array $body = null
    ): array {
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if ($body !== null) {
                $options[CURLOPT_POSTFIELDS] = json_encode($body);
            }
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new LLMProviderException(
                "cURL error: {$error}",
                LLMProviderException::TYPE_NETWORK,
                $this->getId()
            );
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            throw LLMProviderException::fromHttpStatus(
                $httpCode,
                $decoded['error']['message'] ?? $response,
                $this->getId(),
                null,
                ['response' => $decoded]
            );
        }

        return $decoded ?? [];
    }

    /**
     * Format headers for curl
     */
    protected function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $formatted[] = "{$key}: {$value}";
        }
        return $formatted;
    }

    /**
     * Get API key from config or environment
     */
    protected function getApiKey(): string
    {
        $key = $this->config['api_key'] ?? null;

        if (!$key) {
            $envKey = strtoupper($this->getId()) . '_API_KEY';
            $key = getenv($envKey) ?: ($_ENV[$envKey] ?? '');
        }

        if (!$key) {
            throw new LLMProviderException(
                "API key not configured for {$this->getId()}",
                LLMProviderException::TYPE_AUTH,
                $this->getId()
            );
        }

        return $key;
    }

    /**
     * Get API base URL
     */
    protected function getApiBase(): string
    {
        return $this->config['api_base'] ?? '';
    }

    /**
     * Apply safety settings based on mode
     */
    protected function applySafetySettings(array $options): array
    {
        if ($this->currentMode === 'uncensored') {
            // Try to minimize safety restrictions
            $options['safety_settings'] = $options['safety_settings'] ?? [];
        }

        return $options;
    }

    /**
     * Format messages for provider-specific format
     */
    protected function formatMessages(array $messages): array
    {
        // Default implementation returns as-is (OpenAI format)
        return $messages;
    }

    /**
     * Validate messages structure
     */
    protected function validateMessages(array $messages): void
    {
        if (empty($messages)) {
            throw new \InvalidArgumentException('Messages cannot be empty');
        }

        foreach ($messages as $i => $message) {
            if (!isset($message['role']) || !isset($message['content'])) {
                throw new \InvalidArgumentException(
                    "Message at index {$i} must have 'role' and 'content' keys"
                );
            }

            if (!in_array($message['role'], ['system', 'user', 'assistant'])) {
                throw new \InvalidArgumentException(
                    "Invalid role '{$message['role']}' at index {$i}"
                );
            }
        }
    }
}
