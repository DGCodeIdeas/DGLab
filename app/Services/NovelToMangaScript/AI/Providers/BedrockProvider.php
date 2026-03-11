<?php

declare(strict_types=1);

namespace DGLab\Services\NovelToMangaScript\AI\Providers;

use DGLab\Services\NovelToMangaScript\AI\LLMResponse;
use DGLab\Services\NovelToMangaScript\AI\LLMProviderException;

/**
 * Amazon Bedrock LLM Provider
 * 
 * Implements the Amazon Bedrock API for accessing multiple
 * foundation models through a unified AWS interface.
 * 
 * Features:
 * - Access to Claude, Llama, Mistral, and other models
 * - AWS IAM authentication
 * - Regional endpoints
 * - VPC support
 * 
 * @package DGLab\Services\NovelToMangaScript\AI\Providers
 */
class BedrockProvider extends AbstractLLMProvider
{
    /**
     * Provider identifier
     */
    protected string $providerId = 'bedrock';
    
    /**
     * Provider display name
     */
    protected string $providerName = 'Amazon Bedrock';
    
    /**
     * AWS Region
     */
    protected string $region = 'us-east-1';
    
    /**
     * Available models with their specifications
     */
    protected array $availableModels = [
        // Claude models
        'anthropic.claude-3-5-sonnet-20241022-v2:0' => [
            'display_name' => 'Claude 3.5 Sonnet v2',
            'context_window' => 200000,
            'max_output' => 8192,
            'supports_json_mode' => true,
            'supports_vision' => true,
            'cost_per_1k_input' => 0.003,
            'cost_per_1k_output' => 0.015,
        ],
        'anthropic.claude-3-5-haiku-20241022-v1:0' => [
            'display_name' => 'Claude 3.5 Haiku',
            'context_window' => 200000,
            'max_output' => 8192,
            'supports_json_mode' => true,
            'supports_vision' => true,
            'cost_per_1k_input' => 0.001,
            'cost_per_1k_output' => 0.005,
        ],
        'anthropic.claude-3-opus-20240229-v1:0' => [
            'display_name' => 'Claude 3 Opus',
            'context_window' => 200000,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'supports_vision' => true,
            'cost_per_1k_input' => 0.015,
            'cost_per_1k_output' => 0.075,
        ],
        
        // Llama models
        'meta.llama3-2-90b-instruct-v1:0' => [
            'display_name' => 'Llama 3.2 90B',
            'context_window' => 128000,
            'max_output' => 8192,
            'supports_json_mode' => false,
            'supports_vision' => true,
            'cost_per_1k_input' => 0.002,
            'cost_per_1k_output' => 0.002,
        ],
        'meta.llama3-2-11b-instruct-v1:0' => [
            'display_name' => 'Llama 3.2 11B',
            'context_window' => 128000,
            'max_output' => 8192,
            'supports_json_mode' => false,
            'supports_vision' => true,
            'cost_per_1k_input' => 0.00016,
            'cost_per_1k_output' => 0.00016,
        ],
        'meta.llama3-1-70b-instruct-v1:0' => [
            'display_name' => 'Llama 3.1 70B',
            'context_window' => 128000,
            'max_output' => 2048,
            'supports_json_mode' => false,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.00099,
            'cost_per_1k_output' => 0.00099,
        ],
        
        // Mistral models
        'mistral.mistral-large-2407-v1:0' => [
            'display_name' => 'Mistral Large 2',
            'context_window' => 128000,
            'max_output' => 8192,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.003,
            'cost_per_1k_output' => 0.009,
        ],
        'mistral.mistral-small-2402-v1:0' => [
            'display_name' => 'Mistral Small',
            'context_window' => 32000,
            'max_output' => 8192,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.001,
            'cost_per_1k_output' => 0.003,
        ],
        
        // Amazon Titan
        'amazon.titan-text-premier-v1:0' => [
            'display_name' => 'Amazon Titan Text Premier',
            'context_window' => 32000,
            'max_output' => 3072,
            'supports_json_mode' => false,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.0005,
            'cost_per_1k_output' => 0.0015,
        ],
    ];
    
    /**
     * Default model for this provider
     */
    protected string $defaultModel = 'anthropic.claude-3-5-sonnet-20241022-v2:0';
    
    /**
     * AWS credentials
     */
    protected string $accessKeyId;
    protected string $secretAccessKey;
    protected ?string $sessionToken = null;

    /**
     * Constructor
     */
    public function __construct(string $apiKey = '', array $config = [])
    {
        // For Bedrock, apiKey can be empty as we use IAM
        parent::__construct($apiKey, $config);
        
        $this->region = $config['region'] ?? getenv('AWS_REGION') ?: 'us-east-1';
        $this->accessKeyId = $config['access_key_id'] ?? getenv('AWS_ACCESS_KEY_ID') ?: '';
        $this->secretAccessKey = $config['secret_access_key'] ?? getenv('AWS_SECRET_ACCESS_KEY') ?: '';
        $this->sessionToken = $config['session_token'] ?? getenv('AWS_SESSION_TOKEN') ?: null;
    }

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
        
        // Determine request format based on model provider
        $payload = $this->buildPayload($model, $prompt, $systemPrompt, $options);
        
        $startTime = microtime(true);
        
        try {
            $response = $this->makeRequest($model, $payload);
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
     * Build request payload based on model type
     */
    protected function buildPayload(
        string $model,
        string $prompt,
        ?string $systemPrompt,
        array $options
    ): array {
        $modelSpec = $this->availableModels[$model];
        
        // Claude models use Messages API format
        if (str_starts_with($model, 'anthropic.')) {
            $messages = [['role' => 'user', 'content' => $prompt]];
            
            $payload = [
                'anthropic_version' => 'bedrock-2023-05-31',
                'max_tokens' => min(
                    $options['max_tokens'] ?? 4096,
                    $modelSpec['max_output']
                ),
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.7,
            ];
            
            if ($systemPrompt) {
                $payload['system'] = $systemPrompt;
            }
            
            return $payload;
        }
        
        // Llama models
        if (str_starts_with($model, 'meta.')) {
            $fullPrompt = $systemPrompt 
                ? "<|begin_of_text|><|start_header_id|>system<|end_header_id|>\n{$systemPrompt}<|eot_id|><|start_header_id|>user<|end_header_id|>\n{$prompt}<|eot_id|><|start_header_id|>assistant<|end_header_id|>"
                : "<|begin_of_text|><|start_header_id|>user<|end_header_id|>\n{$prompt}<|eot_id|><|start_header_id|>assistant<|end_header_id|>";
            
            return [
                'prompt' => $fullPrompt,
                'max_gen_len' => min($options['max_tokens'] ?? 2048, $modelSpec['max_output']),
                'temperature' => $options['temperature'] ?? 0.7,
                'top_p' => $options['top_p'] ?? 0.9,
            ];
        }
        
        // Mistral models
        if (str_starts_with($model, 'mistral.')) {
            $messages = [];
            if ($systemPrompt) {
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            }
            $messages[] = ['role' => 'user', 'content' => $prompt];
            
            return [
                'messages' => $messages,
                'max_tokens' => min($options['max_tokens'] ?? 4096, $modelSpec['max_output']),
                'temperature' => $options['temperature'] ?? 0.7,
            ];
        }
        
        // Amazon Titan models
        if (str_starts_with($model, 'amazon.')) {
            $inputText = $systemPrompt 
                ? "System: {$systemPrompt}\n\nUser: {$prompt}"
                : $prompt;
            
            return [
                'inputText' => $inputText,
                'textGenerationConfig' => [
                    'maxTokenCount' => min($options['max_tokens'] ?? 3072, $modelSpec['max_output']),
                    'temperature' => $options['temperature'] ?? 0.7,
                    'topP' => $options['top_p'] ?? 0.9,
                ],
            ];
        }
        
        throw new \InvalidArgumentException("Unsupported model: {$model}");
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
        
        // For Claude, we can use the messages directly
        if (str_starts_with($model, 'anthropic.')) {
            return $this->sendClaudeWithHistory($messages, $systemPrompt, $options);
        }
        
        // For other models, concatenate history into a single prompt
        $prompt = $this->flattenHistory($messages);
        return $this->send($prompt, $systemPrompt, $options);
    }
    
    /**
     * Send with history using Claude's Messages API
     */
    protected function sendClaudeWithHistory(
        array $messages,
        ?string $systemPrompt,
        array $options
    ): LLMResponse {
        $model = $options['model'] ?? $this->defaultModel;
        $modelSpec = $this->availableModels[$model];
        
        $payload = [
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens' => min($options['max_tokens'] ?? 4096, $modelSpec['max_output']),
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
        ];
        
        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }
        
        $startTime = microtime(true);
        $response = $this->makeRequest($model, $payload);
        $latency = (microtime(true) - $startTime) * 1000;
        
        return $this->parseResponse($response, $model, $latency);
    }
    
    /**
     * Flatten message history for models that don't support multi-turn
     */
    protected function flattenHistory(array $messages): string
    {
        $parts = [];
        foreach ($messages as $message) {
            $role = ucfirst($message['role']);
            $parts[] = "{$role}: {$message['content']}";
        }
        return implode("\n\n", $parts);
    }

    /**
     * Make HTTP request to Bedrock API with AWS Signature V4
     */
    protected function makeRequest(string $model, array $payload): array
    {
        $endpoint = "https://bedrock-runtime.{$this->region}.amazonaws.com/model/{$model}/invoke";
        
        $body = json_encode($payload);
        $datetime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        
        // Build canonical request
        $headers = $this->signRequest($endpoint, $body, $datetime, $date);
        
        $ch = curl_init($endpoint);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
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
            $errorMessage = $data['message'] ?? $data['Message'] ?? 'Unknown error';
            throw new \RuntimeException("API error ({$httpCode}): {$errorMessage}", $httpCode);
        }
        
        return $data;
    }
    
    /**
     * Sign request with AWS Signature V4
     */
    protected function signRequest(
        string $endpoint,
        string $body,
        string $datetime,
        string $date
    ): array {
        $parsed = parse_url($endpoint);
        $host = $parsed['host'];
        $path = $parsed['path'];
        $service = 'bedrock';
        
        $payloadHash = hash('sha256', $body);
        
        $canonicalHeaders = "content-type:application/json\nhost:{$host}\nx-amz-date:{$datetime}\n";
        $signedHeaders = 'content-type;host;x-amz-date';
        
        if ($this->sessionToken) {
            $canonicalHeaders .= "x-amz-security-token:{$this->sessionToken}\n";
            $signedHeaders .= ';x-amz-security-token';
        }
        
        $canonicalRequest = "POST\n{$path}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        
        $credentialScope = "{$date}/{$this->region}/{$service}/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$datetime}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        
        $kDate = hash_hmac('sha256', $date, "AWS4{$this->secretAccessKey}", true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKeyId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
        
        $headers = [
            'Content-Type: application/json',
            "Host: {$host}",
            "X-Amz-Date: {$datetime}",
            "Authorization: {$authorization}",
        ];
        
        if ($this->sessionToken) {
            $headers[] = "X-Amz-Security-Token: {$this->sessionToken}";
        }
        
        return $headers;
    }

    /**
     * Parse Bedrock API response
     */
    protected function parseResponse(array $response, string $model, float $latency): LLMResponse
    {
        $content = '';
        $inputTokens = 0;
        $outputTokens = 0;
        $finishReason = 'unknown';
        
        // Parse based on model type
        if (str_starts_with($model, 'anthropic.')) {
            $content = $response['content'][0]['text'] ?? '';
            $inputTokens = $response['usage']['input_tokens'] ?? 0;
            $outputTokens = $response['usage']['output_tokens'] ?? 0;
            $finishReason = $response['stop_reason'] ?? 'unknown';
        } elseif (str_starts_with($model, 'meta.')) {
            $content = $response['generation'] ?? '';
            $inputTokens = $response['prompt_token_count'] ?? 0;
            $outputTokens = $response['generation_token_count'] ?? 0;
            $finishReason = $response['stop_reason'] ?? 'unknown';
        } elseif (str_starts_with($model, 'mistral.')) {
            $content = $response['outputs'][0]['text'] ?? '';
            $finishReason = $response['outputs'][0]['stop_reason'] ?? 'unknown';
        } elseif (str_starts_with($model, 'amazon.')) {
            $content = $response['results'][0]['outputText'] ?? '';
            $inputTokens = $response['inputTextTokenCount'] ?? 0;
            $outputTokens = $response['results'][0]['tokenCount'] ?? 0;
            $finishReason = $response['results'][0]['completionReason'] ?? 'unknown';
        }
        
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
                'region' => $this->region,
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
        return true; // Claude models support JSON mode
    }

    /**
     * {@inheritdoc}
     */
    public function supportsFunctionCalling(): bool
    {
        return true; // Claude models support tools
    }

    /**
     * {@inheritdoc}
     */
    public function supportsVision(): bool
    {
        return true;
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
     * Set AWS region
     */
    public function setRegion(string $region): self
    {
        $this->region = $region;
        return $this;
    }
    
    /**
     * Get current AWS region
     */
    public function getRegion(): string
    {
        return $this->region;
    }
}
