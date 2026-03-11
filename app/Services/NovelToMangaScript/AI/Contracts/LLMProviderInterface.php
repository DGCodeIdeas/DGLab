<?php

/**
 * DGLab LLM Provider Interface
 *
 * Contract for all LLM providers in the MangaScript system.
 *
 * @package DGLab\Services\NovelToMangaScript\AI\Contracts
 */

namespace DGLab\Services\NovelToMangaScript\AI\Contracts;

use DGLab\Services\NovelToMangaScript\AI\LLMResponse;

/**
 * Interface LLMProviderInterface
 *
 * All LLM providers must implement this interface.
 */
interface LLMProviderInterface
{
    /**
     * Get provider identifier
     */
    public function getId(): string;

    /**
     * Get display name
     */
    public function getName(): string;

    /**
     * Get provider category (A-H)
     */
    public function getCategory(): string;

    /**
     * Get provider tier (1-3)
     */
    public function getTier(): int;

    /**
     * Get available models
     *
     * @return array<string, array> Model configurations
     */
    public function getModels(): array;

    /**
     * Check if model is available
     */
    public function hasModel(string $modelId): bool;

    /**
     * Get model configuration
     */
    public function getModelConfig(string $modelId): ?array;

    /**
     * Check if provider supports streaming
     */
    public function supportsStreaming(): bool;

    /**
     * Check if provider supports JSON mode
     */
    public function supportsJsonMode(): bool;

    /**
     * Set operating mode
     *
     * @param string $mode 'censored', 'uncensored', or 'auto'
     */
    public function setMode(string $mode): self;

    /**
     * Get current mode
     */
    public function getMode(): string;

    /**
     * Execute a chat completion
     *
     * @param string $model Model identifier
     * @param array $messages Chat messages
     * @param array $options Additional options
     * @return LLMResponse
     */
    public function chat(string $model, array $messages, array $options = []): LLMResponse;

    /**
     * Execute streaming chat completion
     *
     * @param string $model Model identifier
     * @param array $messages Chat messages
     * @param array $options Additional options
     * @return \Generator<string> Stream of content chunks
     */
    public function chatStream(string $model, array $messages, array $options = []): \Generator;

    /**
     * Test connection to provider
     *
     * @return array{success: bool, latency_ms: float, error?: string}
     */
    public function testConnection(): array;

    /**
     * Get provider status
     *
     * @return array{available: bool, uptime_30d?: float, last_error?: string}
     */
    public function getStatus(): array;

    /**
     * Get pricing information
     *
     * @return array<string, array{input: float, output: float}>
     */
    public function getPricing(): array;

    /**
     * Estimate cost for request
     *
     * @param string $model Model identifier
     * @param int $inputTokens Estimated input tokens
     * @param int $outputTokens Estimated output tokens
     * @return float Cost in USD
     */
    public function estimateCost(string $model, int $inputTokens, int $outputTokens): float;

    /**
     * Get provider metadata
     */
    public function getMetadata(): array;
}
