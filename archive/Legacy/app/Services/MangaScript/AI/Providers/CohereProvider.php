<?php

namespace DGLab\Services\MangaScript\AI\Providers;

use DGLab\Services\MangaScript\AI\Contracts\LLMProviderInterface;
use DGLab\Services\MangaScript\AI\LLMResponse;

class CohereProvider extends AbstractLLMProvider implements LLMProviderInterface
{
    protected string $providerId = 'cohere';

    public function getId(): string
    {
        return $this->providerId;
    }

    public function getName(): string
    {
        return 'Cohere';
    }

    public function getModels(): array
    {
        return [
            'command-r-plus' => ['context_window' => 128000, 'max_output' => 4096],
            'command-r' => ['context_window' => 128000, 'max_output' => 4096],
        ];
    }

    protected function getDefaultModel(): string
    {
        return 'command-r-plus';
    }

    public function chat(string $model, array $messages, array $options = []): LLMResponse
    {
        $this->validateMessages($messages);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        $startTime = microtime(true);
        $response = $this->httpRequest('POST', 'https://api.cohere.com/v2/chat', [
            'Authorization' => 'Bearer ' . $this->getApiKey()
        ], $payload);
        $latency = (microtime(true) - $startTime) * 1000;

        return new LLMResponse(
            content: $response['message']['content'][0]['text'] ?? '',
            finishReason: $response['finish_reason'] ?? 'stop',
            inputTokens: $response['usage']['tokens']['input_tokens'] ?? 0,
            outputTokens: $response['usage']['tokens']['output_tokens'] ?? 0,
            modelUsed: $model,
            providerUsed: $this->providerId,
            latencyMs: $latency
        );
    }

    public function chatStream(string $model, array $messages, array $options = []): \Generator
    {
        $response = $this->chat($model, $messages, $options);
        yield $response->content;
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function supportsJsonMode(): bool
    {
        return true;
    }
}
