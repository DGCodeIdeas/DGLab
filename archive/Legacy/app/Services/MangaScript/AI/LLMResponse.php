<?php

/**
 * DGLab LLM Response
 *
 * Standardized response object from any LLM provider.
 *
 * @package DGLab\Services\MangaScript\AI
 */

namespace DGLab\Services\MangaScript\AI;

/**
 * Class LLMResponse
 *
 * Represents a response from any LLM provider with unified structure.
 */
class LLMResponse
{
    /**
     * Constructor
     */
    public function __construct(
        public readonly string $content,
        public readonly string $finishReason,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly string $modelUsed,
        public readonly string $providerUsed,
        public readonly array $metadata = [],
        public readonly bool $isUncensored = false,
        public readonly ?float $latencyMs = null,
        public readonly ?float $costUsd = null
    ) {
    }

    /**
     * Get total tokens used
     */
    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Check if response was successful
     */
    public function isSuccess(): bool
    {
        return $this->finishReason === 'stop' || $this->finishReason === 'end_turn';
    }

    /**
     * Check if response was truncated
     */
    public function isTruncated(): bool
    {
        return $this->finishReason === 'length' || $this->finishReason === 'max_tokens';
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'finish_reason' => $this->finishReason,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->getTotalTokens(),
            'model_used' => $this->modelUsed,
            'provider_used' => $this->providerUsed,
            'is_uncensored' => $this->isUncensored,
            'latency_ms' => $this->latencyMs,
            'cost_usd' => $this->costUsd,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create from raw provider response
     */
    public static function fromOpenAIFormat(array $raw, string $provider, bool $isUncensored = false): self
    {
        $choice = $raw['choices'][0] ?? [];
        $usage = $raw['usage'] ?? [];

        return new self(
            content: $choice['message']['content'] ?? $choice['text'] ?? '',
            finishReason: $choice['finish_reason'] ?? 'unknown',
            inputTokens: $usage['prompt_tokens'] ?? 0,
            outputTokens: $usage['completion_tokens'] ?? 0,
            modelUsed: $raw['model'] ?? 'unknown',
            providerUsed: $provider,
            metadata: ['raw_response' => $raw],
            isUncensored: $isUncensored
        );
    }

    /**
     * Create from Anthropic format
     */
    public static function fromAnthropicFormat(array $raw, bool $isUncensored = false): self
    {
        $content = '';
        foreach ($raw['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            }
        }

        $usage = $raw['usage'] ?? [];

        return new self(
            content: $content,
            finishReason: $raw['stop_reason'] ?? 'unknown',
            inputTokens: $usage['input_tokens'] ?? 0,
            outputTokens: $usage['output_tokens'] ?? 0,
            modelUsed: $raw['model'] ?? 'unknown',
            providerUsed: 'anthropic',
            metadata: ['raw_response' => $raw],
            isUncensored: $isUncensored
        );
    }

    /**
     * Create from Google Gemini format
     */
    public static function fromGoogleFormat(array $raw, bool $isUncensored = false): self
    {
        $candidate = $raw['candidates'][0] ?? [];
        $content = $candidate['content']['parts'][0]['text'] ?? '';
        $usage = $raw['usageMetadata'] ?? [];

        return new self(
            content: $content,
            finishReason: $candidate['finishReason'] ?? 'unknown',
            inputTokens: $usage['promptTokenCount'] ?? 0,
            outputTokens: $usage['candidatesTokenCount'] ?? 0,
            modelUsed: $raw['modelVersion'] ?? 'gemini',
            providerUsed: 'google_gemini',
            metadata: ['raw_response' => $raw],
            isUncensored: $isUncensored
        );
    }
}
