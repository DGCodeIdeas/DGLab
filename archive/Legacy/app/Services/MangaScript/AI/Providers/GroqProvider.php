<?php

namespace DGLab\Services\MangaScript\AI\Providers;

use DGLab\Services\MangaScript\AI\Contracts\LLMProviderInterface;
use DGLab\Services\MangaScript\AI\LLMResponse;

class GroqProvider extends AbstractLLMProvider implements LLMProviderInterface
{
    protected string $providerId = 'groq';

    public function getId(): string
    {
        return $this->providerId;
    }

    public function getName(): string
    {
        return 'Groq';
    }

    public function getModels(): array
    {
        return [
            'llama3-70b-8192' => ['context_window' => 8192, 'max_output' => 4096],
            'mixtral-8x7b-32768' => ['context_window' => 32768, 'max_output' => 4096],
        ];
    }

    protected function getDefaultModel(): string
    {
        return 'llama3-70b-8192';
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
        $response = $this->httpRequest('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'Authorization' => 'Bearer ' . $this->getApiKey()
        ], $payload);
        $latency = (microtime(true) - $startTime) * 1000;

        return new LLMResponse(
            content: $response['choices'][0]['message']['content'] ?? '',
            finishReason: $response['choices'][0]['finish_reason'] ?? 'stop',
            inputTokens: $response['usage']['prompt_tokens'] ?? 0,
            outputTokens: $response['usage']['completion_tokens'] ?? 0,
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
