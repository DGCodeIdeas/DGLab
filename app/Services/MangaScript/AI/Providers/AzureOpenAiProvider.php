<?php

declare(strict_types=1);

namespace DGLab\Services\MangaScript\AI\Providers;

use DGLab\Services\MangaScript\AI\LLMResponse;
use DGLab\Services\MangaScript\AI\LLMProviderException;

/**
 * Azure OpenAI LLM Provider
 * 
 * Implements the Azure OpenAI Service API for enterprise-grade
 * OpenAI model deployments with Azure security and compliance.
 * 
 * Features:
 * - GPT-4o, GPT-4 Turbo, GPT-3.5 deployments
 * - Regional deployment support
 * - Azure Active Directory authentication
 * - Private endpoint support
 * 
 * @package DGLab\Services\MangaScript\AI\Providers
 */
class AzureOpenAiProvider extends AbstractLLMProvider
{
    /**
     * Provider identifier
     */
    protected string $providerId = 'azure_openai';
    
    /**
     * Provider display name
     */
    protected string $providerName = 'Azure OpenAI';
    
    /**
     * Azure resource name
     */
    protected string $resourceName;
    
    /**
     * API version
     */
    protected string $apiVersion = '2024-10-01-preview';
    
    /**
     * Available deployments (configured per Azure resource)
     * Note: These are template specs - actual models depend on your Azure deployment
     */
    protected array $availableModels = [
        'gpt-4o' => [
            'context_window' => 128000,
            'max_output' => 16384,
            'supports_json_mode' => true,
            'supports_vision' => true,
            'cost_per_1k_input' => 0.0025,
            'cost_per_1k_output' => 0.01,
        ],
        'gpt-4o-mini' => [
            'context_window' => 128000,
            'max_output' => 16384,
            'supports_json_mode' => true,
            'supports_vision' => true,
            'cost_per_1k_input' => 0.00015,
            'cost_per_1k_output' => 0.0006,
        ],
        'gpt-4-turbo' => [
            'context_window' => 128000,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'supports_vision' => true,
            'cost_per_1k_input' => 0.01,
            'cost_per_1k_output' => 0.03,
        ],
        'gpt-4' => [
            'context_window' => 8192,
            'max_output' => 8192,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.03,
            'cost_per_1k_output' => 0.06,
        ],
        'gpt-35-turbo' => [
            'context_window' => 16384,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_per_1k_input' => 0.0005,
            'cost_per_1k_output' => 0.0015,
        ],
    ];
    
    /**
     * Default deployment name
     */
    protected string $defaultModel = 'gpt-4o';
    
    /**
     * Deployment name mapping (deployment name -> model spec key)
     */
    protected array $deploymentMapping = [];

    /**
     * Constructor
     */
    public function __construct(string $apiKey, array $config = [])
    {
        parent::__construct($apiKey, $config);
        
        $this->resourceName = $config['resource_name'] 
            ?? getenv('AZURE_OPENAI_RESOURCE_NAME') 
            ?: throw new \InvalidArgumentException('Azure resource name is required');
            
        $this->apiVersion = $config['api_version'] ?? $this->apiVersion;
        
        // Set up deployment mapping if provided
        if (!empty($config['deployments'])) {
            $this->deploymentMapping = $config['deployments'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function send(
        string $prompt,
        ?string $systemPrompt = null,
        array $options = []
    ): LLMResponse {
        $deploymentName = $options['model'] ?? $options['deployment'] ?? $this->defaultModel;
        $modelSpec = $this->getModelSpec($deploymentName);
        
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
        
        $payload = [
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? $this->config['default_temperature'] ?? 0.7,
            'max_tokens' => min(
                $options['max_tokens'] ?? $this->config['default_max_tokens'] ?? 4096,
                $modelSpec['max_output']
            ),
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
            $response = $this->makeRequest($deploymentName, $payload);
            $latency = (microtime(true) - $startTime) * 1000;
            
            return $this->parseResponse($response, $deploymentName, $latency);
            
        } catch (\Exception $e) {
            throw LLMProviderException::requestFailed(
                $this->providerId,
                $deploymentName,
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
        $deploymentName = $options['model'] ?? $options['deployment'] ?? $this->defaultModel;
        $modelSpec = $this->getModelSpec($deploymentName);
        
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
            $response = $this->makeRequest($deploymentName, $payload);
            $latency = (microtime(true) - $startTime) * 1000;
            
            return $this->parseResponse($response, $deploymentName, $latency);
            
        } catch (\Exception $e) {
            throw LLMProviderException::requestFailed(
                $this->providerId,
                $deploymentName,
                $e->getMessage(),
                $e->getCode()
            );
        }
    }

    /**
     * Get model specification for a deployment
     */
    protected function getModelSpec(string $deploymentName): array
    {
        // Check deployment mapping first
        if (isset($this->deploymentMapping[$deploymentName])) {
            $modelKey = $this->deploymentMapping[$deploymentName];
            if (isset($this->availableModels[$modelKey])) {
                return $this->availableModels[$modelKey];
            }
        }
        
        // Check if deployment name matches a known model
        if (isset($this->availableModels[$deploymentName])) {
            return $this->availableModels[$deploymentName];
        }
        
        // Default to gpt-4o specs
        return $this->availableModels['gpt-4o'];
    }

    /**
     * Make HTTP request to Azure OpenAI API
     */
    protected function makeRequest(string $deploymentName, array $payload): array
    {
        $endpoint = sprintf(
            'https://%s.openai.azure.com/openai/deployments/%s/chat/completions?api-version=%s',
            $this->resourceName,
            $deploymentName,
            $this->apiVersion
        );
        
        $ch = curl_init($endpoint);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'api-key: ' . $this->apiKey,
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
     * Parse Azure OpenAI API response
     */
    protected function parseResponse(array $response, string $deployment, float $latency): LLMResponse
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
        $modelSpec = $this->getModelSpec($deployment);
        $cost = (($inputTokens / 1000) * $modelSpec['cost_per_1k_input']) +
                (($outputTokens / 1000) * $modelSpec['cost_per_1k_output']);
        
        return new LLMResponse(
            content: $content,
            provider: $this->providerId,
            model: $deployment,
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
                'azure_resource' => $this->resourceName,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableModels(): array
    {
        // Return both deployment mappings and known models
        return array_unique(array_merge(
            array_keys($this->deploymentMapping),
            array_keys($this->availableModels)
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getModelCapabilities(string $model): array
    {
        return $this->getModelSpec($model);
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
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsVision(): bool
    {
        return true;
    }

    /**
     * Set deployment mapping
     * 
     * @param array<string, string> $mapping Deployment name -> model spec key
     */
    public function setDeploymentMapping(array $mapping): self
    {
        $this->deploymentMapping = $mapping;
        return $this;
    }
    
    /**
     * Add a deployment
     */
    public function addDeployment(string $deploymentName, string $modelSpecKey): self
    {
        $this->deploymentMapping[$deploymentName] = $modelSpecKey;
        return $this;
    }
    
    /**
     * Get Azure resource name
     */
    public function getResourceName(): string
    {
        return $this->resourceName;
    }
}
