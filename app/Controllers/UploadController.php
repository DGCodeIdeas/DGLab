<?php
/**
 * DGLab PWA - Upload Controller
 * 
 * Handles chunked file uploads.
 * 
 * @package DGLab\Controllers
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Controllers;

use DGLab\Core\Controller;
use DGLab\Core\ChunkedUpload;

/**
 * UploadController Class
 * 
 * Controller for chunked file uploads.
 */
class UploadController extends Controller
{
    /**
     * @var ChunkedUpload $uploader Chunked upload handler
     */
    private ChunkedUpload $uploader;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->uploader = new ChunkedUpload();
    }

    /**
     * Initialize chunked upload
     * 
     * @return void
     */
    public function init(): void
    {
        $filename = $this->input('filename');
        $totalSize = (int) $this->input('total_size');
        $mimeType = $this->input('mime_type');
        
        // Validate inputs
        $errors = [];
        
        if (empty($filename)) {
            $errors[] = 'Filename is required';
        }
        
        if ($totalSize <= 0) {
            $errors[] = 'Total size must be greater than 0';
        }
        
        if (!empty($errors)) {
            $this->error('Validation failed', 400, $errors);
            return;
        }
        
        try {
            $result = $this->uploader->initialize($filename, $totalSize, $mimeType);
            
            $this->success($result, 'Upload initialized');
            
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Upload a chunk
     * 
     * @return void
     */
    public function chunk(): void
    {
        $uploadId = $this->input('upload_id');
        $chunkIndex = (int) $this->input('chunk_index');
        
        if (empty($uploadId)) {
            $this->error('Upload ID is required', 400);
            return;
        }
        
        if (!isset($_FILES['chunk'])) {
            $this->error('No chunk data received', 400);
            return;
        }
        
        try {
            $result = $this->uploader->handleFileChunk($uploadId, $chunkIndex, $_FILES['chunk']);
            
            if ($result['status'] === 'completed') {
                // Store path in session for processing
                $_SESSION['uploads'][$uploadId] = $result;
            }
            
            $this->success($result);
            
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get upload progress
     * 
     * @param string $uploadId Upload ID
     * @return void
     */
    public function progress(string $uploadId): void
    {
        $progress = $this->uploader->getProgress($uploadId);
        
        $this->success($progress);
    }

    /**
     * Cancel upload
     * 
     * @param string $uploadId Upload ID
     * @return void
     */
    public function cancel(string $uploadId): void
    {
        $this->uploader->cleanupChunks($uploadId, true);
        
        // Remove from session
        if (isset($_SESSION['uploads'][$uploadId])) {
            unset($_SESSION['uploads'][$uploadId]);
        }
        
        $this->success(null, 'Upload cancelled');
    }
}
