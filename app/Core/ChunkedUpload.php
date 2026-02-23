<?php
/**
 * DGLab PWA - Chunked Upload Class
 * 
 * The ChunkedUpload class handles large file uploads by breaking them
 * into smaller chunks. This is essential for:
 * - Bypassing PHP upload limits on shared hosting
 * - Resumable uploads
 * - Progress tracking
 * - Memory efficiency
 * 
 * @package DGLab\Core
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Core;

/**
 * ChunkedUpload Class
 * 
 * Manages chunked file uploads with resumable support.
 */
class ChunkedUpload
{
    /**
     * @var string $chunksPath Path to chunks storage
     */
    private string $chunksPath;
    
    /**
     * @var string $uploadsPath Path to final uploads
     */
    private string $uploadsPath;
    
    /**
     * @var int $chunkSize Size of each chunk in bytes
     */
    private int $chunkSize;
    
    /**
     * @var int $maxFileSize Maximum allowed file size
     */
    private int $maxFileSize;
    
    /**
     * @var array $allowedMimeTypes Allowed MIME types
     */
    private array $allowedMimeTypes;
    
    /**
     * @var array $allowedExtensions Allowed file extensions
     */
    private array $allowedExtensions;
    
    /**
     * @var string|null $currentUploadId Current upload session ID
     */
    private ?string $currentUploadId = null;
    
    /**
     * @var array $uploadProgress Upload progress tracking
     */
    private array $uploadProgress = [];

    /**
     * Constructor
     * 
     * @param array $options Upload configuration options
     */
    public function __construct(array $options = [])
    {
        global $config;
        
        $uploadConfig = $config['upload'] ?? [];
        
        $this->chunksPath = $options['chunks_path'] ?? CHUNKS_PATH;
        $this->uploadsPath = $options['uploads_path'] ?? UPLOADS_PATH;
        $this->chunkSize = $options['chunk_size'] ?? ($uploadConfig['chunk_size'] ?? 1024 * 1024); // 1MB default
        $this->maxFileSize = $options['max_file_size'] ?? ($uploadConfig['max_file_size'] ?? 100 * 1024 * 1024); // 100MB default
        $this->allowedMimeTypes = $options['allowed_mime_types'] ?? ($uploadConfig['allowed_mime_types'] ?? ['*/*']);
        $this->allowedExtensions = $options['allowed_extensions'] ?? ($uploadConfig['allowed_extensions'] ?? []);
        
        // Ensure directories exist
        $this->ensureDirectory($this->chunksPath);
        $this->ensureDirectory($this->uploadsPath);
    }

    // =============================================================================
    // UPLOAD INITIALIZATION
    // =============================================================================

    /**
     * Initialize a new chunked upload session
     * 
     * @param string $filename Original filename
     * @param int $totalSize Total file size in bytes
     * @param string|null $mimeType MIME type
     * @return array Upload session info
     */
    public function initialize(string $filename, int $totalSize, ?string $mimeType = null): array
    {
        // Validate file size
        if ($totalSize > $this->maxFileSize) {
            throw new \Exception('File size exceeds maximum allowed size');
        }
        
        // Validate MIME type
        if (!$this->isMimeTypeAllowed($mimeType)) {
            throw new \Exception('File type not allowed');
        }
        
        // Validate extension
        if (!$this->isExtensionAllowed($filename)) {
            throw new \Exception('File extension not allowed');
        }
        
        // Generate upload ID
        $uploadId = $this->generateUploadId();
        
        // Calculate total chunks
        $totalChunks = (int) ceil($totalSize / $this->chunkSize);
        
        // Create upload session directory
        $sessionPath = $this->chunksPath . '/' . $uploadId;
        $this->ensureDirectory($sessionPath);
        
        // Save upload metadata
        $metadata = [
            'upload_id'     => $uploadId,
            'filename'      => $filename,
            'total_size'    => $totalSize,
            'total_chunks'  => $totalChunks,
            'chunk_size'    => $this->chunkSize,
            'mime_type'     => $mimeType,
            'uploaded_chunks' => [],
            'created_at'    => time(),
            'status'        => 'initialized',
        ];
        
        file_put_contents($sessionPath . '/metadata.json', json_encode($metadata));
        
        $this->currentUploadId = $uploadId;
        
        return [
            'upload_id'    => $uploadId,
            'chunk_size'   => $this->chunkSize,
            'total_chunks' => $totalChunks,
            'status'       => 'initialized',
        ];
    }

    // =============================================================================
    // CHUNK UPLOADING
    // =============================================================================

    /**
     * Upload a chunk
     * 
     * @param string $uploadId Upload session ID
     * @param int $chunkIndex Chunk index (0-based)
     * @param string $chunkData Chunk data (raw binary)
     * @return array Upload progress
     */
    public function uploadChunk(string $uploadId, int $chunkIndex, string $chunkData): array
    {
        $sessionPath = $this->chunksPath . '/' . $uploadId;
        $metadataPath = $sessionPath . '/metadata.json';
        
        // Validate upload session
        if (!file_exists($metadataPath)) {
            throw new \Exception('Upload session not found');
        }
        
        $metadata = json_decode(file_get_contents($metadataPath), true);
        
        // Validate chunk index
        if ($chunkIndex < 0 || $chunkIndex >= $metadata['total_chunks']) {
            throw new \Exception('Invalid chunk index');
        }
        
        // Check if chunk already uploaded
        if (in_array($chunkIndex, $metadata['uploaded_chunks'], true)) {
            return $this->getProgress($uploadId);
        }
        
        // Save chunk
        $chunkPath = $sessionPath . '/chunk_' . $chunkIndex;
        file_put_contents($chunkPath, $chunkData);
        
        // Update metadata
        $metadata['uploaded_chunks'][] = $chunkIndex;
        sort($metadata['uploaded_chunks']);
        file_put_contents($metadataPath, json_encode($metadata));
        
        // Check if upload is complete
        if (count($metadata['uploaded_chunks']) === $metadata['total_chunks']) {
            return $this->finalizeUpload($uploadId);
        }
        
        return $this->getProgress($uploadId);
    }

    /**
     * Handle chunk upload from $_FILES
     * 
     * @param string $uploadId Upload session ID
     * @param int $chunkIndex Chunk index
     * @param array $file $_FILES array entry
     * @return array Upload progress
     */
    public function handleFileChunk(string $uploadId, int $chunkIndex, array $file): array
    {
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            throw new \Exception('No file uploaded');
        }
        
        $chunkData = file_get_contents($file['tmp_name']);
        
        return $this->uploadChunk($uploadId, $chunkIndex, $chunkData);
    }

    // =============================================================================
    // UPLOAD FINALIZATION
    // =============================================================================

    /**
     * Finalize upload and combine chunks
     * 
     * @param string $uploadId Upload session ID
     * @return array Final upload info
     */
    public function finalizeUpload(string $uploadId): array
    {
        $sessionPath = $this->chunksPath . '/' . $uploadId;
        $metadataPath = $sessionPath . '/metadata.json';
        
        if (!file_exists($metadataPath)) {
            throw new \Exception('Upload session not found');
        }
        
        $metadata = json_decode(file_get_contents($metadataPath), true);
        
        // Verify all chunks are present
        if (count($metadata['uploaded_chunks']) !== $metadata['total_chunks']) {
            return $this->getProgress($uploadId);
        }
        
        // Generate safe filename
        $safeFilename = $this->generateSafeFilename($metadata['filename']);
        $outputPath = $this->uploadsPath . '/' . $safeFilename;
        
        // Combine chunks
        $outputHandle = fopen($outputPath, 'wb');
        
        if (!$outputHandle) {
            throw new \Exception('Failed to create output file');
        }
        
        for ($i = 0; $i < $metadata['total_chunks']; $i++) {
            $chunkPath = $sessionPath . '/chunk_' . $i;
            
            if (!file_exists($chunkPath)) {
                fclose($outputHandle);
                unlink($outputPath);
                throw new \Exception("Missing chunk: {$i}");
            }
            
            fwrite($outputHandle, file_get_contents($chunkPath));
        }
        
        fclose($outputHandle);
        
        // Update metadata
        $metadata['status'] = 'completed';
        $metadata['output_path'] = $outputPath;
        $metadata['output_filename'] = $safeFilename;
        $metadata['completed_at'] = time();
        file_put_contents($metadataPath, json_encode($metadata));
        
        // Clean up chunks
        $this->cleanupChunks($uploadId, false);
        
        return [
            'upload_id'       => $uploadId,
            'status'          => 'completed',
            'filename'        => $safeFilename,
            'path'            => $outputPath,
            'size'            => filesize($outputPath),
            'mime_type'       => $metadata['mime_type'],
        ];
    }

    // =============================================================================
    // PROGRESS TRACKING
    // =============================================================================

    /**
     * Get upload progress
     * 
     * @param string $uploadId Upload session ID
     * @return array Progress info
     */
    public function getProgress(string $uploadId): array
    {
        $sessionPath = $this->chunksPath . '/' . $uploadId;
        $metadataPath = $sessionPath . '/metadata.json';
        
        if (!file_exists($metadataPath)) {
            return [
                'upload_id' => $uploadId,
                'status'    => 'not_found',
            ];
        }
        
        $metadata = json_decode(file_get_contents($metadataPath), true);
        
        $uploadedChunks = count($metadata['uploaded_chunks']);
        $totalChunks = $metadata['total_chunks'];
        $progress = $totalChunks > 0 ? round(($uploadedChunks / $totalChunks) * 100, 2) : 0;
        
        return [
            'upload_id'       => $uploadId,
            'status'          => $metadata['status'],
            'filename'        => $metadata['filename'],
            'total_size'      => $metadata['total_size'],
            'total_chunks'    => $totalChunks,
            'uploaded_chunks' => $uploadedChunks,
            'progress'        => $progress,
            'missing_chunks'  => $this->getMissingChunks($metadata),
        ];
    }

    /**
     * Get missing chunk indices
     * 
     * @param array $metadata Upload metadata
     * @return array Missing chunk indices
     */
    private function getMissingChunks(array $metadata): array
    {
        $allChunks = range(0, $metadata['total_chunks'] - 1);
        return array_diff($allChunks, $metadata['uploaded_chunks']);
    }

    // =============================================================================
    // RESUME FUNCTIONALITY
    // =============================================================================

    /**
     * Resume an existing upload
     * 
     * @param string $uploadId Upload session ID
     * @return array Resume info
     */
    public function resume(string $uploadId): array
    {
        $progress = $this->getProgress($uploadId);
        
        if ($progress['status'] === 'not_found') {
            throw new \Exception('Upload session not found');
        }
        
        if ($progress['status'] === 'completed') {
            return $progress;
        }
        
        return [
            'upload_id'       => $uploadId,
            'status'          => 'resumable',
            'chunk_size'      => $this->chunkSize,
            'missing_chunks'  => $progress['missing_chunks'],
            'progress'        => $progress['progress'],
        ];
    }

    // =============================================================================
    // CLEANUP METHODS
    // =============================================================================

    /**
     * Clean up chunks for an upload
     * 
     * @param string $uploadId Upload session ID
     * @param bool $removeMetadata Whether to remove metadata file
     * @return void
     */
    public function cleanupChunks(string $uploadId, bool $removeMetadata = true): void
    {
        $sessionPath = $this->chunksPath . '/' . $uploadId;
        
        if (!is_dir($sessionPath)) {
            return;
        }
        
        // Remove chunk files
        $files = glob($sessionPath . '/chunk_*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        // Remove metadata if requested
        if ($removeMetadata && file_exists($sessionPath . '/metadata.json')) {
            unlink($sessionPath . '/metadata.json');
        }
        
        // Remove directory if empty
        if ($removeMetadata) {
            @rmdir($sessionPath);
        }
    }

    /**
     * Clean up old upload sessions
     * 
     * @param int $maxAge Maximum age in seconds (default: 24 hours)
     * @return int Number of sessions cleaned
     */
    public function cleanupOldSessions(int $maxAge = 86400): int
    {
        $count = 0;
        $now = time();
        
        $sessions = glob($this->chunksPath . '/*', GLOB_ONLYDIR);
        
        foreach ($sessions as $sessionPath) {
            $metadataPath = $sessionPath . '/metadata.json';
            
            if (!file_exists($metadataPath)) {
                continue;
            }
            
            $metadata = json_decode(file_get_contents($metadataPath), true);
            $age = $now - ($metadata['created_at'] ?? 0);
            
            if ($age > $maxAge) {
                $uploadId = basename($sessionPath);
                $this->cleanupChunks($uploadId, true);
                $count++;
            }
        }
        
        return $count;
    }

    // =============================================================================
    // VALIDATION METHODS
    // =============================================================================

    /**
     * Check if MIME type is allowed
     * 
     * @param string|null $mimeType MIME type to check
     * @return bool True if allowed
     */
    private function isMimeTypeAllowed(?string $mimeType): bool
    {
        if ($mimeType === null) {
            return true;
        }
        
        // Allow all if wildcard
        if (in_array('*/*', $this->allowedMimeTypes, true)) {
            return true;
        }
        
        // Check exact match
        if (in_array($mimeType, $this->allowedMimeTypes, true)) {
            return true;
        }
        
        // Check wildcard patterns (e.g., "image/*")
        foreach ($this->allowedMimeTypes as $allowed) {
            if (strpos($allowed, '/*') !== false) {
                $prefix = substr($allowed, 0, strpos($allowed, '/*'));
                if (strpos($mimeType, $prefix . '/') === 0) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if file extension is allowed
     * 
     * @param string $filename Filename to check
     * @return bool True if allowed
     */
    private function isExtensionAllowed(string $filename): bool
    {
        if (empty($this->allowedExtensions)) {
            return true;
        }
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        return in_array($extension, $this->allowedExtensions, true);
    }

    // =============================================================================
    // UTILITY METHODS
    // =============================================================================

    /**
     * Generate unique upload ID
     * 
     * @return string Upload ID
     */
    private function generateUploadId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate safe filename
     * 
     * @param string $filename Original filename
     * @return string Safe filename
     */
    private function generateSafeFilename(string $filename): string
    {
        // Get extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Generate base name
        $baseName = bin2hex(random_bytes(8));
        
        // Add original name (sanitized)
        $originalName = pathinfo($filename, PATHINFO_FILENAME);
        $originalName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $originalName = substr($originalName, 0, 50);
        
        return $baseName . '_' . $originalName . ($extension ? '.' . $extension : '');
    }

    /**
     * Ensure directory exists
     * 
     * @param string $path Directory path
     * @return void
     */
    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    // =============================================================================
    // GETTER METHODS
    // =============================================================================

    /**
     * Get chunk size
     * 
     * @return int Chunk size in bytes
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * Get maximum file size
     * 
     * @return int Maximum file size in bytes
     */
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * Get current upload ID
     * 
     * @return string|null Current upload ID
     */
    public function getCurrentUploadId(): ?string
    {
        return $this->currentUploadId;
    }
}
