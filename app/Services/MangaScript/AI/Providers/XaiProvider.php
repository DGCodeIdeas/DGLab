<?php

namespace DGLab\Services\MangaScript\AI\Providers;

use DGLab\Services\MangaScript\AI\Contracts\LLMProviderInterface;
use DGLab\Services\MangaScript\AI\LLMResponse;

class XaiProvider extends AbstractLLMProvider implements LLMProviderInterface
{
    protected string $providerId = 'xai';

    public function getId(): string { return $this->providerId; }
    public function getName(): string { return 'xAI (Grok)'; }

    public function getModels(): array
    {
        return [
            'grok-1' => ['context_window' => 128000, 'max_output' => 4096],
        ];
    }

    protected function getDefaultModel(): string { return 'grok-1'; }

    public function chat(string $model, array $messages, array $options = []): LLMResponse
    {
        $this->validateMessages($messages);
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        $startTime = microtime(true);
        $response = $this->httpRequest('POST', 'https://api.x.ai/v1/chat/completions', [
            'Authorization' => 'Bearer ' . $this->getApiKey()
        ], $payload);
        $latency = (microtime(true) - $startTime) * 1000;

        return new LLMResponse(
            content: $response['choices'][0]['message']['content'] ?? '',
            provider: $this->providerId,
            model: $model,
            inputTokens: $response['usage']['prompt_tokens'] ?? 0,
            outputTokens: $response['usage']['completion_tokens'] ?? 0,
            totalTokens: $response['usage']['total_tokens'] ?? 0,
            cost: 0,
            latencyMs: $latency,
            finishReason: $response['choices'][0]['finish_reason'] ?? 'stop'
        );
    }

    public function chatStream(string $model, array $messages, array $options = []): \Generator
    {
        $response = $this->chat($model, $messages, $options);
        yield $response->content;
    }

    public function supportsStreaming(): bool { return true; }
    public function supportsJsonMode(): bool { return true; }
}
