<?php

/**
 * DGLab Custom AI Provider
 *
 * Universal custom AI endpoint support for user-defined APIs.
 *
 * @package DGLab\Services\NovelToMangaScript\AI\Providers
 */

namespace DGLab\Services\NovelToMangaScript\AI\Providers;

use DGLab\Services\NovelToMangaScript\AI\LLMResponse;
use DGLab\Services\NovelToMangaScript\AI\LLMProviderException;

/**
 * Class CustomProvider
 *
 * Universal custom AI endpoint implementation.
 * Supports OpenAI-compatible, Anthropic-compatible, Google-compatible, and fully custom formats.
 */
class CustomProvider extends AbstractLLMProvider
{
    /**
     * Endpoint configuration
     */
    private array $endpointConfig;

    /**
     * Endpoint ID
     */
    private string $endpointId;

    /**
     * Constructor
     */
    public function __construct(array $config, string $endpointId = 'default')
    {
        parent::__construct($config);
        $this->endpointId = $endpointId;
        $this->endpointConfig = $config['endpoints'][$endpointId] ?? $config;
    }

    /**
     * Get provider ID
     */
    public function getId(): string
    {
        return 'custom_' . $this->endpointId;
    }

    /**
     * Get display name
     */
    public function getName(): string
    {
        return $this->endpointConfig['display_name'] ?? 'Custom: ' . $this->endpointId;
    }

    /**
     * Get category
     */
    public function getCategory(): string
    {
        return 'H';
    }

    /**
     * Get tier
     */
    public function getTier(): int
    {
        return (int) ($this->endpointConfig['tier'] ?? 3);
    }

    /**
     * Get available models
     */
    public function getModels(): array
    {
        $models = $this->endpointConfig['models'] ?? [];

        if (empty($models) && isset($this->endpointConfig['default_model'])) {
            $models[$this->endpointConfig['default_model']] = [
                'censorship_level' => $this->endpointConfig['censorship_level'] ?? 0,
                'cost_input' => $this->endpointConfig['cost_input'] ?? 0,
                'cost_output' => $this->endpointConfig['cost_output'] ?? 0,
                'context_tokens' => $this->endpointConfig['context_tokens'] ?? 4096,
                'speed_tier' => $this->endpointConfig['speed_tier'] ?? 'standard',
                'specializations' => $this->endpointConfig['specializations'] ?? ['general'],
            ];
        }

        return $models;
    }

    /**
     * Check if streaming is supported
     */
    public function supportsStreaming(): bool
    {
        return $this->endpointConfig['capabilities']['streaming'] ?? false;
    }

    /**
     * Check if JSON mode is supported
     */
    public function supportsJsonMode(): bool
    {
        return $this->endpointConfig['capabilities']['json_mode'] ?? false;
    }

    /**
     * Get default model
     */
    protected function getDefaultModel(): string
    {
        return $this->endpointConfig['default_model'] ?? array_key_first($this->getModels()) ?? 'default';
    }

    /**
     * Execute chat completion
     */
    public function chat(string $model, array $messages, array $options = []): LLMResponse
    {
        $this->validateMessages($messages);

        // Build request according to endpoint's expected format
        $requestFormat = $this->endpointConfig['request_format'] ?? 'openai';

        $payload = match ($requestFormat) {
            'openai' => $this->buildOpenAIFormat($model, $messages, $options),
            'anthropic' => $this->buildAnthropicFormat($model, $messages, $options),
            'google' => $this->buildGoogleFormat($model, $messages, $options),
            'custom' => $this->buildCustomFormat($model, $messages, $options),
            default => throw new \InvalidArgumentException("Unknown format: $requestFormat")
        };

        // Apply custom transformations
        $payload = $this->applyCustomMappings($payload);

        // Get endpoint URL
        $url = $this->getEndpointUrl();

        // Build headers
        $headers = $this->buildHeaders();

        // Execute request
        $transport = $this->endpointConfig['transport'] ?? 'http';

        $start = microtime(true);

        $rawResponse = match ($transport) {
            'http', 'https' => $this->httpRequest('POST', $url, $headers, $payload),
            default => throw new \InvalidArgumentException("Unsupported transport: $transport")
        };

        $latency = (microtime(true) - $start) * 1000;

        // Parse response according to endpoint's format
        $llmResponse = $this->parseCustomResponse($rawResponse, $requestFormat);

        $cost = $this->estimateCost($model, $llmResponse->inputTokens, $llmResponse->outputTokens);

        return new LLMResponse(
            content: $llmResponse->content,
            finishReason: $llmResponse->finishReason,
            inputTokens: $llmResponse->inputTokens,
            outputTokens: $llmResponse->outputTokens,
            modelUsed: $model,
            providerUsed: $this->getName(),
            metadata: $llmResponse->metadata,
            isUncensored: $this->currentMode === 'uncensored',
            latencyMs: $latency,
            costUsd: $cost
        );
    }

    /**
     * Execute streaming chat completion
     */
    public function chatStream(string $model, array $messages, array $options = []): \Generator
    {
        if (!$this->supportsStreaming()) {
            // Fallback to non-streaming
            $response = $this->chat($model, $messages, $options);
            yield $response->content;
            return;
        }

        $this->validateMessages($messages);

        $requestFormat = $this->endpointConfig['request_format'] ?? 'openai';
        $options['stream'] = true;

        $payload = match ($requestFormat) {
            'openai' => $this->buildOpenAIFormat($model, $messages, $options),
            'anthropic' => $this->buildAnthropicFormat($model, $messages, $options),
            'google' => $this->buildGoogleFormat($model, $messages, $options),
            'custom' => $this->buildCustomFormat($model, $messages, $options),
            default => throw new \InvalidArgumentException("Unknown format: $requestFormat")
        };

        $url = $this->getEndpointUrl();
        $headers = $this->buildHeaders();

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$buffer) {
                $buffer .= $data;
                return strlen($data);
            },
        ]);

        $buffer = '';
        curl_exec($ch);
        curl_close($ch);

        // Parse stream based on format
        yield from $this->parseStreamResponse($buffer, $requestFormat);
    }

    /**
     * Build OpenAI format request
     */
    private function buildOpenAIFormat(string $model, array $messages, array $options): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        if ($options['stream'] ?? false) {
            $payload['stream'] = true;
        }

        if ($options['json_mode'] ?? false) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        return $payload;
    }

    /**
     * Build Anthropic format request
     */
    private function buildAnthropicFormat(string $model, array $messages, array $options): array
    {
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

        $payload = [
            'model' => $model,
            'messages' => $chatMessages,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        if ($systemMessage) {
            $payload['system'] = $systemMessage;
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        if ($options['stream'] ?? false) {
            $payload['stream'] = true;
        }

        return $payload;
    }

    /**
     * Build Google format request
     */
    private function buildGoogleFormat(string $model, array $messages, array $options): array
    {
        $contents = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                continue;
            }

            $role = $message['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $message['content']]],
            ];
        }

        return [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 4096,
            ],
        ];
    }

    /**
     * Build fully custom format request
     */
    private function buildCustomFormat(string $model, array $messages, array $options): array
    {
        $format = $this->endpointConfig['custom_format'] ?? [];
        $payload = [];

        // Map fields according to custom schema
        $mapping = $format['field_mapping'] ?? [];

        foreach ($mapping as $source => $target) {
            $value = match ($source) {
                'messages' => $this->transformMessages($messages, $format['message_format'] ?? 'openai'),
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 4096,
                'model' => $model,
                default => $options[$source] ?? null
            };

            if ($value !== null) {
                $this->setNestedValue($payload, $target, $value);
            }
        }

        // Add static fields
        foreach ($format['static_fields'] ?? [] as $key => $value) {
            $this->setNestedValue($payload, $key, $value);
        }

        return $payload;
    }

    /**
     * Apply custom mappings and transformations
     */
    private function applyCustomMappings(array $payload): array
    {
        // Apply payload transformations
        if (isset($this->endpointConfig['payload_transform'])) {
            $transforms = $this->endpointConfig['payload_transform'];

            foreach ($transforms as $transform) {
                $type = $transform['type'] ?? '';

                switch ($type) {
                    case 'rename_field':
                        $from = $transform['from'] ?? '';
                        $to = $transform['to'] ?? '';
                        if (isset($payload[$from])) {
                            $payload[$to] = $payload[$from];
                            unset($payload[$from]);
                        }
                        break;

                    case 'add_field':
                        $field = $transform['field'] ?? '';
                        $value = $transform['value'] ?? '';
                        $this->setNestedValue($payload, $field, $value);
                        break;

                    case 'wrap':
                        $wrapper = $transform['wrapper'] ?? '';
                        $payload = [$wrapper => $payload];
                        break;
                }
            }
        }

        return $payload;
    }

    /**
     * Build request headers
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        // Add authentication
        $authType = $this->endpointConfig['auth_type'] ?? 'bearer';
        $apiKey = $this->getApiKey();

        switch ($authType) {
            case 'bearer':
                $headers['Authorization'] = 'Bearer ' . $apiKey;
                break;
            case 'x-api-key':
                $headers['x-api-key'] = $apiKey;
                break;
            case 'api_key_header':
                $headerName = $this->endpointConfig['api_key_header'] ?? 'X-API-Key';
                $headers[$headerName] = $apiKey;
                break;
            case 'basic':
                $headers['Authorization'] = 'Basic ' . base64_encode($apiKey);
                break;
            case 'none':
                // No authentication
                break;
        }

        // Add custom header injections
        foreach ($this->endpointConfig['header_injections'] ?? [] as $header => $value) {
            // Support dynamic values
            $processedValue = str_replace(
                ['{timestamp}', '{request_id}'],
                [time(), uniqid()],
                $value
            );
            $headers[$header] = $processedValue;
        }

        return $headers;
    }

    /**
     * Get endpoint URL
     */
    private function getEndpointUrl(): string
    {
        $baseUrl = $this->endpointConfig['api_base'] ?? '';
        $endpoint = $this->endpointConfig['endpoint_path'] ?? '/chat/completions';

        if (empty($baseUrl)) {
            throw new LLMProviderException(
                'Custom endpoint base URL not configured',
                LLMProviderException::TYPE_INVALID_REQUEST,
                $this->getId()
            );
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    /**
     * Parse custom response
     */
    private function parseCustomResponse(array $raw, string $format): LLMResponse
    {
        $extractRules = $this->endpointConfig['response_extraction'] ?? [];

        // Use format-specific defaults if no custom rules
        if (empty($extractRules)) {
            return match ($format) {
                'openai' => LLMResponse::fromOpenAIFormat($raw, $this->getName(), $this->currentMode === 'uncensored'),
                'anthropic' => LLMResponse::fromAnthropicFormat($raw, $this->currentMode === 'uncensored'),
                'google' => LLMResponse::fromGoogleFormat($raw, $this->currentMode === 'uncensored'),
                default => $this->parseGenericResponse($raw)
            };
        }

        // Custom extraction
        $content = $this->extractValue($raw, $extractRules['content_path'] ?? 'choices.0.message.content');
        $finishReason = $this->extractValue($raw, $extractRules['finish_reason_path'] ?? 'choices.0.finish_reason');
        $inputTokens = $this->extractValue($raw, $extractRules['input_tokens_path'] ?? 'usage.prompt_tokens');
        $outputTokens = $this->extractValue($raw, $extractRules['output_tokens_path'] ?? 'usage.completion_tokens');

        // Handle error paths
        if (isset($extractRules['error_path'])) {
            $error = $this->extractValue($raw, $extractRules['error_path']);
            if ($error) {
                throw new LLMProviderException(
                    "Endpoint error: $error",
                    LLMProviderException::TYPE_SERVER_ERROR,
                    $this->getId()
                );
            }
        }

        return new LLMResponse(
            content: (string) $content,
            finishReason: (string) ($finishReason ?? 'unknown'),
            inputTokens: (int) ($inputTokens ?? 0),
            outputTokens: (int) ($outputTokens ?? 0),
            modelUsed: $this->extractValue($raw, $extractRules['model_path'] ?? 'model') ?? 'unknown',
            providerUsed: $this->getName(),
            metadata: ['raw_response' => $raw],
            isUncensored: $this->currentMode === 'uncensored'
        );
    }

    /**
     * Parse generic response
     */
    private function parseGenericResponse(array $raw): LLMResponse
    {
        // Try common patterns
        $content = $raw['content']
            ?? $raw['text']
            ?? $raw['message']
            ?? $raw['output']
            ?? $raw['response']
            ?? '';

        if (is_array($content)) {
            $content = $content['text'] ?? $content['content'] ?? json_encode($content);
        }

        return new LLMResponse(
            content: (string) $content,
            finishReason: 'unknown',
            inputTokens: 0,
            outputTokens: 0,
            modelUsed: 'unknown',
            providerUsed: $this->getName(),
            metadata: ['raw_response' => $raw],
            isUncensored: $this->currentMode === 'uncensored'
        );
    }

    /**
     * Parse streaming response
     */
    private function parseStreamResponse(string $buffer, string $format): \Generator
    {
        $lines = explode("\n", $buffer);

        foreach ($lines as $line) {
            if (str_starts_with($line, 'data: ')) {
                $json = substr($line, 6);
                if ($json === '[DONE]') {
                    break;
                }

                $data = json_decode($json, true);
                if (!$data) {
                    continue;
                }

                $content = match ($format) {
                    'openai' => $data['choices'][0]['delta']['content'] ?? null,
                    'anthropic' => $data['delta']['text'] ?? null,
                    'google' => $data['candidates'][0]['content']['parts'][0]['text'] ?? null,
                    default => $data['content'] ?? $data['text'] ?? null
                };

                if ($content !== null) {
                    yield $content;
                }
            }
        }
    }

    /**
     * Transform messages for different formats
     */
    private function transformMessages(array $messages, string $format): array
    {
        return match ($format) {
            'anthropic' => array_map(function ($m) {
                if ($m['role'] === 'system') {
                    return null;
                }
                return ['role' => $m['role'], 'content' => $m['content']];
            }, $messages),
            'google' => array_map(function ($m) {
                $role = $m['role'] === 'assistant' ? 'model' : 'user';
                return ['role' => $role, 'parts' => [['text' => $m['content']]]];
            }, array_filter($messages, fn($m) => $m['role'] !== 'system')),
            default => $messages
        };
    }

    /**
     * Extract value from nested array using dot notation
     */
    private function extractValue(array $data, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $data;

        foreach ($parts as $part) {
            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } elseif (is_object($current) && isset($current->$part)) {
                $current = $current->$part;
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Set nested value using dot notation
     */
    private function setNestedValue(array &$data, string $path, mixed $value): void
    {
        $parts = explode('.', $path);
        $current = &$data;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
    }

    /**
     * Get API key
     */
    protected function getApiKey(): string
    {
        // Check endpoint config first
        if (!empty($this->endpointConfig['api_key'])) {
            return $this->endpointConfig['api_key'];
        }

        // Check environment variable
        $envKey = $this->endpointConfig['api_key_env'] ?? 'CUSTOM_LLM_API_KEY';
        $key = getenv($envKey) ?: ($_ENV[$envKey] ?? '');

        if (empty($key) && ($this->endpointConfig['auth_type'] ?? 'bearer') !== 'none') {
            throw new LLMProviderException(
                "API key not configured for custom endpoint {$this->endpointId}",
                LLMProviderException::TYPE_AUTH,
                $this->getId()
            );
        }

        return $key;
    }

    /**
     * Test connection with diagnostic output
     */
    public function testConnection(): array
    {
        $start = microtime(true);
        $diagnostics = [];

        try {
            // Check URL
            $url = $this->getEndpointUrl();
            $diagnostics['url'] = $url;

            // Check auth
            $authType = $this->endpointConfig['auth_type'] ?? 'bearer';
            $diagnostics['auth_type'] = $authType;

            if ($authType !== 'none') {
                $apiKey = $this->getApiKey();
                $diagnostics['api_key_set'] = !empty($apiKey);
            }

            // Test request
            $response = $this->chat(
                $this->getDefaultModel(),
                [['role' => 'user', 'content' => 'Say "OK"']],
                ['max_tokens' => 5]
            );

            $latency = (microtime(true) - $start) * 1000;

            return [
                'success' => true,
                'latency_ms' => round($latency, 2),
                'diagnostics' => $diagnostics,
                'response_preview' => substr($response->content, 0, 50),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'latency_ms' => (microtime(true) - $start) * 1000,
                'error' => $e->getMessage(),
                'diagnostics' => $diagnostics,
            ];
        }
    }
}
