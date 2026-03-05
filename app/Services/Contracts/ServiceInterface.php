<?php
/**
 * DGLab Service Interface
 * 
 * Defines the contract that all services must implement.
 * Services are the core processing units of the DGLab application.
 * 
 * @package DGLab\Services\Contracts
 */

namespace DGLab\Services\Contracts;

/**
 * Interface ServiceInterface
 * 
 * All services must implement this interface to be registered
 * with the ServiceRegistry and accessible via the API.
 * 
 * Implementation Requirements:
 * - getId() must return a unique identifier (snake_case recommended)
 * - getName() should return a human-readable name
 * - getInputSchema() must return a valid JSON Schema
 * - validate() must throw ValidationException on invalid input
 * - process() should handle all processing logic and return results
 */
interface ServiceInterface
{
    /**
     * Get the unique service identifier
     * 
     * This ID is used for:
     * - API endpoint routing (/api/services/{id})
     * - Service registration in the registry
     * - Database job tracking
     * - Frontend service loading
     * 
     * @return string Unique service identifier (e.g., 'epub-font-changer')
     */
    public function getId(): string;

    /**
     * Get the display name
     * 
     * Human-readable name shown in the UI.
     * 
     * @return string Service display name (e.g., 'EPUB Font Changer')
     */
    public function getName(): string;

    /**
     * Get the service description
     * 
     * Brief description of what the service does.
     * 
     * @return string Service description
     */
    public function getDescription(): string;

    /**
     * Get the service icon
     * 
     * Returns an icon identifier (Font Awesome class, SVG path, or URL).
     * 
     * @return string Icon identifier
     */
    public function getIcon(): string;

    /**
     * Get the input schema
     * 
     * Returns a JSON Schema object defining the expected input.
     * Used for:
     * - API validation
     * - Dynamic form generation
     * - Documentation
     * 
     * Example schema:
     * {
     *   "type": "object",
     *   "properties": {
     *     "file": {"type": "string", "format": "binary"},
     *     "font": {"type": "string", "enum": ["opendyslexic", "merriweather"]}
     *   },
     *   "required": ["file"]
     * }
     * 
     * @return array JSON Schema as associative array
     */
    public function getInputSchema(): array;

    /**
     * Validate input data
     * 
     * Validates input against the schema. Should throw ValidationException
     * if validation fails.
     * 
     * @param array $input The input data to validate
     * @return array Validated and sanitized input
     * @throws \DGLab\Core\ValidationException If validation fails
     */
    public function validate(array $input): array;

    /**
     * Process the service request
     * 
     * Main processing logic. This method should:
     * - Handle the actual service operation
     * - Update job progress if applicable
     * - Return results in a consistent format
     * - Throw exceptions on errors
     * 
     * @param array $input Validated input data
     * @param callable|null $progressCallback Optional progress callback (0-100)
     * @return array Processing results
     * @throws \Exception If processing fails
     */
    public function process(array $input, ?callable $progressCallback = null): array;

    /**
     * Check if service supports chunked upload
     * 
     * @return bool True if chunked upload is supported
     */
    public function supportsChunking(): bool;

    /**
     * Estimate processing time
     * 
     * Provides an estimated processing time in seconds based on input.
     * Used for UI feedback and queue prioritization.
     * 
     * @param array $input The input data
     * @return int Estimated time in seconds
     */
    public function estimateTime(array $input): int;

    /**
     * Get service configuration
     * 
     * Returns service-specific configuration options.
     * 
     * @return array Configuration array
     */
    public function getConfig(): array;

    /**
     * Get service metadata
     * 
     * Returns additional metadata for the service.
     * 
     * @return array Metadata array
     */
    public function getMetadata(): array;
}
