<?php
/**
 * DGLab PWA - Tool Controller
 * 
 * Handles tool-related requests and processing.
 * 
 * @package DGLab\Controllers
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Controllers;

use DGLab\Core\Controller;
use DGLab\Core\ChunkedUpload;
use DGLab\Tools\ToolRegistry;

/**
 * ToolController Class
 * 
 * Controller for tool pages and processing.
 */
class ToolController extends Controller
{
    /**
     * @var ToolRegistry $registry Tool registry
     */
    private ToolRegistry $registry;
    
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
        
        $this->registry = ToolRegistry::getInstance();
        $this->uploader = new ChunkedUpload();
    }

    /**
     * Tools listing page
     * 
     * @return void
     */
    public function index(): void
    {
        $tools = $this->registry->getAll();
        $categories = $this->registry->getGroupedByCategory();
        
        $this->render('tools/index', [
            'title'      => 'All Tools',
            'tools'      => $tools,
            'categories' => $categories,
        ]);
    }

    /**
     * Tool category page
     * 
     * @param string $category Category name
     * @return void
     */
    public function category(string $category): void
    {
        $tools = $this->registry->getByCategory($category);
        
        if (empty($tools)) {
            $this->redirect('/tools');
            return;
        }
        
        $this->render('tools/category', [
            'title'    => $category . ' Tools',
            'category' => $category,
            'tools'    => $tools,
        ]);
    }

    /**
     * Tool detail/show page
     * 
     * @param string $id Tool ID
     * @return void
     */
    public function show(string $id): void
    {
        $tool = $this->registry->get($id);
        
        if ($tool === null) {
            $this->render('errors/404', [
                'title' => 'Tool Not Found',
            ]);
            return;
        }
        
        $metadata = $this->registry->getMetadata($id);
        
        $this->render('tools/show', [
            'title'    => $tool->getName(),
            'tool'     => $tool,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Process tool request
     * 
     * @param string $id Tool ID
     * @return void
     */
    public function process(string $id): void
    {
        $tool = $this->registry->get($id);
        
        if ($tool === null) {
            $this->error('Tool not found', 404);
            return;
        }
        
        // Get uploaded file
        $uploadId = $this->input('upload_id');
        $inputPath = null;
        
        if ($uploadId) {
            // Get from chunked upload
            $progress = $this->uploader->getProgress($uploadId);
            if ($progress['status'] === 'completed') {
                $inputPath = $progress['path'] ?? null;
            }
        } elseif (isset($_FILES['file'])) {
            // Direct file upload
            $file = $_FILES['file'];
            $inputPath = $file['tmp_name'];
        }
        
        if ($inputPath === null || !file_exists($inputPath)) {
            $this->error('No file uploaded', 400);
            return;
        }
        
        // Validate file
        $validation = $tool->validate($inputPath, $this->all());
        
        if (!$validation['valid']) {
            $this->error('Validation failed', 400, $validation['errors']);
            return;
        }
        
        // Get options
        $options = $this->getToolOptions($tool);
        
        // Process file
        try {
            $result = $tool->process($inputPath, $options);
            
            if ($result['success']) {
                // Store output info in session for download
                $_SESSION['downloads'][$result['job_id']] = [
                    'path'     => $result['output_path'],
                    'filename' => $result['output_filename'],
                    'tool_id'  => $id,
                ];
                
                $this->success([
                    'job_id'          => $result['job_id'],
                    'output_filename' => $result['output_filename'],
                    'file_size'       => $result['file_size'],
                    'processing_time' => $result['processing_time'],
                    'download_url'    => $this->view->helper('url', "/tool/{$id}/download/" . urlencode($result['output_filename'])),
                ], 'Processing completed successfully');
            } else {
                $this->error($result['error'] ?? 'Processing failed', 500);
            }
            
        } catch (\Exception $e) {
            $this->error('Processing failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get tool processing progress
     * 
     * @param string $id Tool ID
     * @param string $jobId Job ID
     * @return void
     */
    public function progress(string $id, string $jobId): void
    {
        $tool = $this->registry->get($id);
        
        if ($tool === null) {
            $this->error('Tool not found', 404);
            return;
        }
        
        $progress = $tool->getProgress($jobId);
        
        $this->success($progress);
    }

    /**
     * Download processed file
     * 
     * @param string $id Tool ID
     * @param string $filename Filename
     * @return void
     */
    public function download(string $id, string $filename): void
    {
        // Find download info from session
        $downloadInfo = null;
        
        if (isset($_SESSION['downloads'])) {
            foreach ($_SESSION['downloads'] as $jobId => $info) {
                if ($info['tool_id'] === $id && $info['filename'] === $filename) {
                    $downloadInfo = $info;
                    break;
                }
            }
        }
        
        if ($downloadInfo === null || !file_exists($downloadInfo['path'])) {
            $this->render('errors/404', [
                'title' => 'File Not Found',
            ]);
            return;
        }
        
        // Send file
        $path = $downloadInfo['path'];
        $filename = $downloadInfo['filename'];
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache, must-revalidate');
        
        readfile($path);
        exit;
    }

    /**
     * Get tool options from request
     * 
     * @param mixed $tool Tool instance
     * @return array Options
     */
    private function getToolOptions($tool): array
    {
        $schema = $tool->getConfigSchema();
        $options = [];
        
        foreach ($schema as $key => $config) {
            $value = $this->input($key);
            
            if ($value !== null) {
                // Type conversion
                switch ($config['type']) {
                    case 'boolean':
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'number':
                        $value = is_float($config['default'] ?? 0) 
                            ? (float) $value 
                            : (int) $value;
                        break;
                    case 'integer':
                        $value = (int) $value;
                        break;
                }
                
                $options[$key] = $value;
            }
        }
        
        return $options;
    }
}
