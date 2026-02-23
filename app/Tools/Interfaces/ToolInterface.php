<?php
/**
 * DGLab PWA - Tool Interface
 * 
 * This interface defines the contract that all tools must implement.
 * It ensures consistency across the tool system and enables easy
 * addition of new tools.
 * 
 * @package DGLab\Tools\Interfaces
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\Interfaces;

/**
 * ToolInterface
 * 
 * All tools in the DGLab PWA must implement this interface.
 */
interface ToolInterface
{
    /**
     * Get the unique identifier for this tool
     * 
     * @return string Tool ID (lowercase, alphanumeric with hyphens)
     */
    public function getId(): string;

    /**
     * Get the display name of the tool
     * 
     * @return string Human-readable tool name
     */
    public function getName(): string;

    /**
     * Get the tool description
     * 
     * @return string Tool description
     */
    public function getDescription(): string;

    /**
     * Get the tool icon (Font Awesome class or SVG path)
     * 
     * @return string Icon identifier
     */
    public function getIcon(): string;

    /**
     * Get the tool category
     * 
     * @return string Category name
     */
    public function getCategory(): string;

    /**
     * Get supported file types/mime types
     * 
     * @return array Array of supported MIME types or extensions
     */
    public function getSupportedTypes(): array;

    /**
     * Get maximum file size allowed (in bytes)
     * 
     * @return int Maximum file size
     */
    public function getMaxFileSize(): int;

    /**
     * Check if tool supports chunked uploads
     * 
     * @return bool True if chunked uploads supported
     */
    public function supportsChunking(): bool;

    /**
     * Process uploaded file
     * 
     * @param string $inputPath Path to input file
     * @param array $options Processing options
     * @return array Processing result with output file info
     * @throws \Exception If processing fails
     */
    public function process(string $inputPath, array $options = []): array;

    /**
     * Validate input file before processing
     * 
     * @param string $inputPath Path to input file
     * @param array $options Validation options
     * @return array Validation result ['valid' => bool, 'errors' => array]
     */
    public function validate(string $inputPath, array $options = []): array;

    /**
     * Get tool configuration options
     * 
     * @return array Configuration options schema
     */
    public function getConfigSchema(): array;

    /**
     * Get default configuration values
     * 
     * @return array Default configuration
     */
    public function getDefaultConfig(): array;

    /**
     * Get processing progress (for long-running operations)
     * 
     * @param string $jobId Job identifier
     * @return array Progress info
     */
    public function getProgress(string $jobId): array;

    /**
     * Clean up temporary files
     * 
     * @param string|null $jobId Specific job ID or null for all
     * @return void
     */
    public function cleanup(?string $jobId = null): void;
}
