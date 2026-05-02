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
     */
    protected array $availableModels = [
        'gpt-4o' => [
            'context_tokens' => 128000,
            'max_output' => 16384,
            'supports_json_mode' => true,
            'supports_vision' => true,
            'cost_input' => 0.0025,
            'cost_output' => 0.01,
        ],
        'gpt-4o-mini' => [
            'context_tokens' => 128000,
            'max_output' => 16384,
            'supports_json_mode' => true,
            'supports_vision' => true,
            'cost_input' => 0.00015,
            'cost_output' => 0.0006,
        ],
        'gpt-4-turbo' => [
            'context_tokens' => 128000,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'supports_vision' => true,
            'cost_input' => 0.01,
            'cost_output' => 0.03,
        ],
        'gpt-4' => [
            'context_tokens' => 8192,
            'max_output' => 8192,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_input' => 0.03,
            'cost_output' => 0.06,
        ],
        'gpt-35-turbo' => [
            'context_tokens' => 16384,
            'max_output' => 4096,
            'supports_json_mode' => true,
            'supports_vision' => false,
            'cost_input' => 0.0005,
            'cost_output' => 0.0015,
        ],
    ];

    /**
     * Deployment name mapping (deployment name -> model spec key)
     */
    protected array $deploymentMapping = [];

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->resourceName = $config['resource_name']
            ?? (string) getenv('AZURE_OPENAI_RESOURCE_NAME')
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
    public function getId(): string
    {
        return $this->providerId;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->providerName;
    }

    /**
     * {@inheritdoc}
     */
    public function getModels(): array
    {
        return $this->availableModels;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultModel(): string
    {
        return 'gpt-4o';
    }

    /**
     * {@inheritdoc}
     */
    public function chat(string $model, array $messages, array $options = []): LLMResponse
    {
        $this->validateMessages($messages);

        $deploymentName = $options['deployment'] ?? $model;
        $modelSpec = $this->getModelSpec($deploymentName);

        $payload = [
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => min(
                $options['max_tokens'] ?? 4096,
                $modelSpec['max_output'] ?? 4096
            ),
            'stream' => false,
        ];

        if (($options['json_mode'] ?? false) && ($modelSpec['supports_json_mode'] ?? false)) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $startTime = microtime(true);

        try {
            $response = $this->makeAzureRequest($deploymentName, $payload);
            $latency = (microtime(true) - $startTime) * 1000;

            $llmResponse = LLMResponse::fromOpenAIFormat($response, $this->providerId);

            // Re-calculate cost based on Azure model spec
            $cost = $this->estimateCost($deploymentName, $llmResponse->inputTokens, $llmResponse->outputTokens);

            return new LLMResponse(
                content: $llmResponse->content,
                finishReason: $llmResponse->finishReason,
                inputTokens: $llmResponse->inputTokens,
                outputTokens: $llmResponse->outputTokens,
                modelUsed: $deploymentName,
                providerUsed: $this->providerId,
                metadata: array_merge($llmResponse->metadata, ['azure_resource' => $this->resourceName]),
                latencyMs: $latency,
                costUsd: $cost
            );
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
    public function chatStream(string $model, array $messages, array $options = []): \Generator
    {
        $this->validateMessages($messages);

        $deploymentName = $options['deployment'] ?? $model;
        $modelSpec = $this->getModelSpec($deploymentName);

        $payload = [
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => min(
                $options['max_tokens'] ?? 4096,
                $modelSpec['max_output'] ?? 4096
            ),
            'stream' => true,
        ];

        $endpoint = sprintf(
            'https://%s.openai.azure.com/openai/deployments/%s/chat/completions?api-version=%s',
            $this->resourceName,
            $deploymentName,
            $this->apiVersion
        );

        $ch = curl_init($endpoint);
        $buffer = '';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'api-key: ' . $this->getApiKey(),
            ],
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$buffer) {
                $buffer .= $data;
                return strlen($data);
            },
        ]);

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
     * Get model specification for a deployment
     */
    protected function getModelSpec(string $deploymentName): array
    {
        if (isset($this->deploymentMapping[$deploymentName])) {
            $modelKey = $this->deploymentMapping[$deploymentName];
            if (isset($this->availableModels[$modelKey])) {
                return $this->availableModels[$modelKey];
            }
        }

        if (isset($this->availableModels[$deploymentName])) {
            return $this->availableModels[$deploymentName];
        }

        return $this->availableModels['gpt-4o'];
    }

    /**
     * Make HTTP request to Azure OpenAI API
     */
    protected function makeAzureRequest(string $deploymentName, array $payload): array
    {
        $endpoint = sprintf(
            'https://%s.openai.azure.com/openai/deployments/%s/chat/completions?api-version=%s',
            $this->resourceName,
            $deploymentName,
            $this->apiVersion
        );

        $headers = [
            'Content-Type: application/json',
            'api-key: ' . $this->getApiKey(),
        ];

        return $this->httpRequest('POST', $endpoint, $headers, $payload);
    }

    /**
     * {@inheritdoc}
     */
    public function estimateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $modelSpec = $this->getModelSpec($model);

        $inputCost = ($inputTokens / 1000) * ($modelSpec['cost_input'] ?? 0);
        $outputCost = ($outputTokens / 1000) * ($modelSpec['cost_output'] ?? 0);

        return $inputCost + $outputCost;
    }
}
