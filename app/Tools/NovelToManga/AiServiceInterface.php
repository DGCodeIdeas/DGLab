<?php
/**
 * DGLab PWA - AI Service Interface
 * 
 * Interface for AI service implementations.
 * 
 * @package DGLab\Tools\NovelToManga
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\NovelToManga;

/**
 * AiServiceInterface
 * 
 * All AI service implementations must implement this interface.
 */
interface AiServiceInterface
{
    /**
     * Convert novel text to manga script
     * 
     * @param array $chunk Text chunk with context
     * @param array $config Processing configuration
     * @return array Manga script structure
     * @throws \Exception If processing fails
     */
    public function convertToMangaScript(array $chunk, array $config): array;

    /**
     * Check if service is available
     * 
     * @return bool True if service is ready
     */
    public function isAvailable(): bool;

    /**
     * Get service name
     * 
     * @return string Service name
     */
    public function getName(): string;

    /**
     * Get service model
     * 
     * @return string Model name
     */
    public function getModel(): string;

    /**
     * Get last error message
     * 
     * @return string|null Error message or null
     */
    public function getLastError(): ?string;

    /**
     * Estimate cost for processing
     * 
     * @param int $tokenCount Number of tokens
     * @return float Estimated cost in USD
     */
    public function estimateCost(int $tokenCount): float;
}
