<?php
/**
 * DGLab Chunked Service Interface
 * 
 * Extends ServiceInterface for services that support chunked processing.
 * Chunked processing is used for large files that need to be processed
 * in parts to manage memory and provide progress feedback.
 * 
 * @package DGLab\Services\Contracts
 */

namespace DGLab\Services\Contracts;

/**
 * Interface ChunkedServiceInterface
 * 
 * Services implementing this interface can process large inputs
 * in chunks, providing better memory management and progress tracking.
 * 
 * Implementation Requirements:
 * - initializeChunkedProcess() must create a session and return session ID
 * - processChunk() must handle a single chunk and update progress
 * - finalizeChunkedProcess() must assemble chunks and complete processing
 * - cancelChunkedProcess() must clean up any temporary resources
 */
interface ChunkedServiceInterface extends ServiceInterface
{
    /**
     * Initialize a chunked processing session
     * 
     * Called before any chunks are uploaded. Should:
     * - Validate the overall request
     * - Create a session record
     * - Allocate temporary storage
     * - Return session information
     * 
     * @param array $metadata Session metadata (file info, options, etc.)
     * @return array Session data including:
     *   - session_id: string Unique session identifier
     *   - chunk_size: int Recommended chunk size in bytes
     *   - total_chunks: int Expected number of chunks
     *   - expires_at: string Session expiration timestamp
     */
    public function initializeChunkedProcess(array $metadata): array;

    /**
     * Process a single chunk
     * 
     * Called for each chunk uploaded. Should:
     * - Validate the chunk
     * - Store the chunk data
     * - Update progress
     * - Return current status
     * 
     * @param string $sessionId The session identifier
     * @param int $chunkIndex The chunk index (0-based)
     * @param string $chunkData The chunk data (binary)
     * @return array Status data including:
     *   - success: bool Whether chunk was processed
     *   - progress: int Overall progress percentage (0-100)
     *   - received_chunks: int Number of chunks received
     *   - total_chunks: int Total expected chunks
     *   - missing_chunks: array Indices of missing chunks
     */
    public function processChunk(string $sessionId, int $chunkIndex, string $chunkData): array;

    /**
     * Finalize the chunked process
     * 
     * Called when all chunks are uploaded. Should:
     * - Assemble all chunks
     * - Perform final processing
     * - Clean up temporary storage
     * - Return final results
     * 
     * @param string $sessionId The session identifier
     * @return array Final processing results
     * @throws \Exception If finalization fails
     */
    public function finalizeChunkedProcess(string $sessionId): array;

    /**
     * Cancel a chunked process
     * 
     * Called when the user cancels or session expires. Should:
     * - Clean up all temporary resources
     * - Delete session record
     * - Release any locks
     * 
     * @param string $sessionId The session identifier
     * @return bool True if cancellation was successful
     */
    public function cancelChunkedProcess(string $sessionId): bool;

    /**
     * Get the status of a chunked process
     * 
     * @param string $sessionId The session identifier
     * @return array Status data including:
     *   - status: string Current status (active, completed, expired, cancelled)
     *   - progress: int Progress percentage (0-100)
     *   - received_chunks: int Number of chunks received
     *   - total_chunks: int Total expected chunks
     *   - missing_chunks: array Indices of missing chunks
     *   - expires_at: string Session expiration timestamp
     */
    public function getChunkedStatus(string $sessionId): array;

    /**
     * Get recommended chunk size
     * 
     * Returns the recommended chunk size in bytes for this service.
     * 
     * @return int Chunk size in bytes (default: 1MB)
     */
    public function getChunkSize(): int;

    /**
     * Get maximum file size
     * 
     * Returns the maximum allowed file size in bytes.
     * 
     * @return int Maximum file size in bytes
     */
    public function getMaxFileSize(): int;

    /**
     * Check if a chunk is valid
     * 
     * Validates a chunk before processing.
     * 
     * @param string $sessionId The session identifier
     * @param int $chunkIndex The chunk index
     * @param string $chunkData The chunk data
     * @return bool True if chunk is valid
     */
    public function isChunkValid(string $sessionId, int $chunkIndex, string $chunkData): bool;
}
