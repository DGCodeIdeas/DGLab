<?php
/**
 * DGLab PWA - Novel to Manga Script Converter
 * 
 * This tool converts text-based novels into structured manga scripts using AI.
 * Features:
 * - Contextual chunking for AI token limits
 * - Multiple AI provider support (OpenAI, Claude, etc.)
 * - Censored/Uncensored processing modes
 * - Custom API key management
 * - Free tier support
 * 
 * @package DGLab\Tools\NovelToManga
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\NovelToManga;

use DGLab\Tools\Interfaces\ToolInterface;
use DGLab\Tools\EpubFontChanger\EpubParser;
use DGLab\Core\Database;

/**
 * NovelToManga Class
 * 
 * Main tool class for converting novels to manga scripts.
 */
class NovelToManga implements ToolInterface
{
    /**
     * @var string $tempPath Path for temporary files
     */
    private string $tempPath;
    
    /**
     * @var string $exportsPath Path for output files
     */
    private string $exportsPath;
    
    /**
     * @var EpubParser $parser EPUB parser instance
     */
    private EpubParser $parser;
    
    /**
     * @var TextChunker $chunker Text chunking handler
     */
    private TextChunker $chunker;
    
    /**
     * @var MangaScriptFormatter $formatter Output formatter
     */
    private MangaScriptFormatter $formatter;
    
    /**
     * @var AiServiceFactory $aiFactory AI service factory
     */
    private AiServiceFactory $aiFactory;
    
    /**
     * @var ApiKeyManager $keyManager API key manager
     */
    private ApiKeyManager $keyManager;
    
    /**
     * @var array $jobs Active processing jobs
     */
    private array $jobs = [];
    
    /**
     * @var Database|null $db Database instance
     */
    private ?Database $db = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->tempPath = STORAGE_PATH . '/temp/novel-to-manga';
        $this->exportsPath = EXPORTS_PATH . '/novel-to-manga';
        
        // Ensure directories exist
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
        if (!is_dir($this->exportsPath)) {
            mkdir($this->exportsPath, 0755, true);
        }
        
        // Initialize components
        $this->parser = new EpubParser();
        $this->chunker = new TextChunker();
        $this->formatter = new MangaScriptFormatter();
        $this->aiFactory = new AiServiceFactory();
        $this->keyManager = new ApiKeyManager();
        
        // Initialize database if available
        try {
            $this->db = Database::getInstance();
            $this->initializeDatabase();
        } catch (\Exception $e) {
            // Database not available, use file-based storage
        }
    }

    // =============================================================================
    // TOOL INTERFACE IMPLEMENTATION
    // =============================================================================

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'novel-to-manga';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Novel to Manga Script';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Convert text-based novels into structured manga scripts using AI. ' .
               'Features contextual chunking, multiple AI providers, and customizable processing modes.';
    }

    /**
     * {@inheritdoc}
     */
    public function getIcon(): string
    {
        return 'fa-book-open';
    }

    /**
     * {@inheritdoc}
     */
    public function getCategory(): string
    {
        return 'E-Books';
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTypes(): array
    {
        return [
            'application/epub+zip',
            'application/epub',
            '.epub',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxFileSize(): int
    {
        return 50 * 1024 * 1024; // 50MB
    }

    /**
     * {@inheritdoc}
     */
    public function supportsChunking(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigSchema(): array
    {
        return [
            'ai_provider' => [
                'type'        => 'select',
                'label'       => 'AI Provider',
                'description' => 'Select the AI service for processing',
                'options'     => [
                    'openai'    => 'OpenAI GPT (Free/Paid)',
                    'claude'    => 'Anthropic Claude (Custom Key)',
                    'gemini'    => 'Google Gemini (Custom Key)',
                ],
                'required'    => true,
                'default'     => 'openai',
            ],
            'ai_model' => [
                'type'        => 'select',
                'label'       => 'AI Model',
                'description' => 'Select the AI model for processing',
                'options'     => [
                    'gpt-4o'            => 'GPT-4o (Best Quality)',
                    'gpt-4o-mini'       => 'GPT-4o Mini (Fast)',
                    'claude-3-opus'     => 'Claude 3 Opus',
                    'claude-3-sonnet'   => 'Claude 3 Sonnet',
                    'gemini-pro'        => 'Gemini Pro',
                ],
                'required'    => true,
                'default'     => 'gpt-4o-mini',
            ],
            'content_mode' => [
                'type'        => 'select',
                'label'       => 'Content Mode',
                'description' => 'Select content filtering level',
                'options'     => [
                    'censored'   => 'Censored (Safe Content Only)',
                    'uncensored' => 'Uncensored (Mature Content Allowed)',
                ],
                'required'    => true,
                'default'     => 'censored',
            ],
            'use_custom_key' => [
                'type'        => 'boolean',
                'label'       => 'Use Custom API Key',
                'description' => 'Use your own API key for processing',
                'required'    => false,
                'default'     => false,
            ],
            'custom_api_key' => [
                'type'        => 'password',
                'label'       => 'Custom API Key',
                'description' => 'Your personal API key (stored securely)',
                'required'    => false,
                'conditional' => ['use_custom_key' => true],
            ],
            'chunk_size' => [
                'type'        => 'select',
                'label'       => 'Chunk Size',
                'description' => 'Text chunk size for AI processing',
                'options'     => [
                    '2000'  => '2,000 tokens (Fast)',
                    '4000'  => '4,000 tokens (Balanced)',
                    '8000'  => '8,000 tokens (Best Context)',
                ],
                'required'    => true,
                'default'     => '4000',
            ],
            'preserve_chapters' => [
                'type'        => 'boolean',
                'label'       => 'Preserve Chapter Structure',
                'description' => 'Maintain original chapter divisions',
                'required'    => false,
                'default'     => true,
            ],
            'include_descriptions' => [
                'type'        => 'boolean',
                'label'       => 'Include Scene Descriptions',
                'description' => 'Add visual scene descriptions',
                'required'    => false,
                'default'     => true,
            ],
            'dialogue_style' => [
                'type'        => 'select',
                'label'       => 'Dialogue Style',
                'description' => 'Format for character dialogue',
                'options'     => [
                    'standard'   => 'Standard Manga Format',
                    'dramatic'   => 'Dramatic/Emphasis',
                    'minimal'    => 'Minimal/Simple',
                ],
                'required'    => true,
                'default'     => 'standard',
            ],
            'output_format' => [
                'type'        => 'select',
                'label'       => 'Output Format',
                'description' => 'Format of the manga script',
                'options'     => [
                    'epub'       => 'EPUB E-book',
                    'script'     => 'Screenplay Format',
                    'detailed'   => 'Detailed Storyboard',
                ],
                'required'    => true,
                'default'     => 'epub',
            ],
            'language' => [
                'type'        => 'select',
                'label'       => 'Output Language',
                'description' => 'Language for the manga script',
                'options'     => [
                    'auto'       => 'Auto-detect from source',
                    'en'         => 'English',
                    'ja'         => 'Japanese',
                    'ko'         => 'Korean',
                    'zh'         => 'Chinese',
                    'es'         => 'Spanish',
                    'fr'         => 'French',
                    'de'         => 'German',
                ],
                'required'    => true,
                'default'     => 'auto',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultConfig(): array
    {
        return [
            'ai_provider'          => 'openai',
            'ai_model'             => 'gpt-4o-mini',
            'content_mode'         => 'censored',
            'use_custom_key'       => false,
            'chunk_size'           => '4000',
            'preserve_chapters'    => true,
            'include_descriptions' => true,
            'dialogue_style'       => 'standard',
            'output_format'        => 'epub',
            'language'             => 'auto',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function validate(string $inputPath, array $options = []): array
    {
        $errors = [];
        
        // Check file exists
        if (!file_exists($inputPath)) {
            return [
                'valid'  => false,
                'errors' => ['File not found: ' . $inputPath],
            ];
        }
        
        // Check file size
        $fileSize = filesize($inputPath);
        if ($fileSize > $this->getMaxFileSize()) {
            $errors[] = 'File size exceeds maximum allowed size of ' . 
                        number_format($this->getMaxFileSize() / 1024 / 1024, 0) . ' MB';
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
        if ($extension !== 'epub') {
            $errors[] = 'Invalid file type. Only EPUB files are supported.';
        }
        
        // Validate EPUB structure
        try {
            $epubInfo = $this->parser->parse($inputPath);
            
            if (!$epubInfo['valid']) {
                $errors[] = 'Invalid EPUB structure: ' . ($epubInfo['error'] ?? 'Unknown error');
            }
            
            // Check if EPUB has readable content
            if (empty($epubInfo['html_files'])) {
                $errors[] = 'No readable content found in EPUB.';
            }
            
        } catch (\Exception $e) {
            $errors[] = 'Failed to parse EPUB: ' . $e->getMessage();
        }
        
        // Validate API key if using custom key
        if (!empty($options['use_custom_key']) && !empty($options['custom_api_key'])) {
            $provider = $options['ai_provider'] ?? 'openai';
            if (!$this->validateApiKey($options['custom_api_key'], $provider)) {
                $errors[] = 'Invalid API key format for selected provider.';
            }
        }
        
        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function process(string $inputPath, array $options = []): array
    {
        $jobId = $this->generateJobId();
        $startTime = microtime(true);
        
        try {
            // Merge with defaults
            $config = array_merge($this->getDefaultConfig(), $options);
            
            // Store job info
            $this->jobs[$jobId] = [
                'id'         => $jobId,
                'status'     => 'processing',
                'progress'   => 0,
                'started'    => $startTime,
                'stage'      => 'initializing',
            ];
            
            // Create working directory
            $workDir = $this->tempPath . '/' . $jobId;
            if (!is_dir($workDir)) {
                mkdir($workDir, 0755, true);
            }
            
            // Step 1: Extract EPUB
            $this->updateProgress($jobId, 5, 'Extracting EPUB...');
            $extractPath = $workDir . '/extracted';
            $this->parser->extract($inputPath, $extractPath);
            
            // Step 2: Parse EPUB and extract text
            $this->updateProgress($jobId, 10, 'Parsing content...');
            $epubInfo = $this->parser->parse($inputPath);
            $novelContent = $this->extractNovelContent($extractPath, $epubInfo);
            
            // Step 3: Chunk text for AI processing
            $this->updateProgress($jobId, 15, 'Segmenting text...');
            $chunkSize = (int) $config['chunk_size'];
            $chunks = $this->chunker->chunk($novelContent, $chunkSize);
            
            // Step 4: Initialize AI service
            $this->updateProgress($jobId, 20, 'Initializing AI service...');
            $aiService = $this->initializeAiService($config);
            
            // Step 5: Process chunks with AI
            $totalChunks = count($chunks);
            $processedChunks = [];
            
            foreach ($chunks as $index => $chunk) {
                $progress = 20 + (60 * ($index / $totalChunks));
                $this->updateProgress($jobId, (int) $progress, 
                    sprintf('Processing chunk %d of %d...', $index + 1, $totalChunks));
                
                $mangaScript = $aiService->convertToMangaScript($chunk, $config);
                $processedChunks[] = $mangaScript;
                
                // Small delay to prevent rate limiting
                if ($index < $totalChunks - 1) {
                    usleep(100000); // 100ms
                }
            }
            
            // Step 6: Combine and format output
            $this->updateProgress($jobId, 85, 'Formatting output...');
            $combinedScript = $this->formatter->combine($processedChunks, $config);
            
            // Step 7: Generate output EPUB
            $this->updateProgress($jobId, 90, 'Creating output file...');
            $originalName = pathinfo($inputPath, PATHINFO_FILENAME);
            $outputName = $originalName . '_MangaScript.epub';
            $outputPath = $this->exportsPath . '/' . $outputName;
            
            $this->createOutputEpub($extractPath, $combinedScript, $outputPath, $config);
            
            // Step 8: Validate output
            $this->updateProgress($jobId, 95, 'Validating output...');
            $validation = $this->validateOutput($outputPath);
            
            if (!$validation['valid']) {
                throw new \Exception('Output validation failed: ' . implode(', ', $validation['errors']));
            }
            
            $this->updateProgress($jobId, 100, 'Complete');
            
            // Calculate processing time
            $processingTime = round(microtime(true) - $startTime, 3);
            
            // Update job status
            $this->jobs[$jobId]['status'] = 'completed';
            $this->jobs[$jobId]['output'] = $outputPath;
            
            // Clean up working directory
            $this->cleanup($jobId);
            
            return [
                'success'         => true,
                'job_id'          => $jobId,
                'output_path'     => $outputPath,
                'output_filename' => $outputName,
                'file_size'       => filesize($outputPath),
                'processing_time' => $processingTime,
                'chunks_processed'=> $totalChunks,
                'validation'      => $validation,
            ];
            
        } catch (\Exception $e) {
            // Update job status
            if (isset($this->jobs[$jobId])) {
                $this->jobs[$jobId]['status'] = 'failed';
                $this->jobs[$jobId]['error'] = $e->getMessage();
            }
            
            // Clean up on failure
            $this->cleanup($jobId);
            
            return [
                'success' => false,
                'job_id'  => $jobId,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getProgress(string $jobId): array
    {
        if (!isset($this->jobs[$jobId])) {
            return [
                'found'    => false,
                'job_id'   => $jobId,
                'status'   => 'unknown',
                'progress' => 0,
            ];
        }
        
        $job = $this->jobs[$jobId];
        
        return [
            'found'    => true,
            'job_id'   => $jobId,
            'status'   => $job['status'],
            'progress' => $job['progress'] ?? 0,
            'message'  => $job['message'] ?? '',
            'stage'    => $job['stage'] ?? '',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function cleanup(?string $jobId = null): void
    {
        if ($jobId !== null) {
            $workDir = $this->tempPath . '/' . $jobId;
            if (is_dir($workDir)) {
                $this->recursiveDelete($workDir);
            }
            unset($this->jobs[$jobId]);
        } else {
            // Clean up all jobs older than 1 hour
            $cutoff = time() - 3600;
            foreach ($this->jobs as $id => $job) {
                if (isset($job['started']) && $job['started'] < $cutoff) {
                    $this->cleanup($id);
                }
            }
        }
    }

    // =============================================================================
    // API KEY MANAGEMENT
    // =============================================================================

    /**
     * Save custom API key for a user
     * 
     * @param string $userId User identifier (session ID or user ID)
     * @param string $provider AI provider name
     * @param string $apiKey API key to store
     * @return bool Success status
     */
    public function saveApiKey(string $userId, string $provider, string $apiKey): bool
    {
        return $this->keyManager->saveKey($userId, $provider, $apiKey);
    }

    /**
     * Get stored API key for a user
     * 
     * @param string $userId User identifier
     * @param string $provider AI provider name
     * @return string|null API key or null
     */
    public function getApiKey(string $userId, string $provider): ?string
    {
        return $this->keyManager->getKey($userId, $provider);
    }

    /**
     * Delete stored API key
     * 
     * @param string $userId User identifier
     * @param string $provider AI provider name
     * @return bool Success status
     */
    public function deleteApiKey(string $userId, string $provider): bool
    {
        return $this->keyManager->deleteKey($userId, $provider);
    }

    /**
     * Check if user has stored API key
     * 
     * @param string $userId User identifier
     * @param string $provider AI provider name
     * @return bool True if key exists
     */
    public function hasApiKey(string $userId, string $provider): bool
    {
        return $this->keyManager->hasKey($userId, $provider);
    }

    // =============================================================================
    // PRIVATE METHODS
    // =============================================================================

    /**
     * Initialize database tables
     * 
     * @return void
     */
    private function initializeDatabase(): void
    {
        if ($this->db === null) {
            return;
        }
        
        // Create API keys table
        $sql = "CREATE TABLE IF NOT EXISTS novel_to_manga_api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(255) NOT NULL,
            provider VARCHAR(50) NOT NULL,
            api_key TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_provider (user_id, provider)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->db->query($sql);
        } catch (\Exception $e) {
            // Table may already exist
        }
    }

    /**
     * Extract novel content from EPUB
     * 
     * @param string $extractPath Extracted EPUB path
     * @param array $epubInfo EPUB information
     * @return array Content chapters
     */
    private function extractNovelContent(string $extractPath, array $epubInfo): array
    {
        $content = [];
        
        foreach ($epubInfo['spine'] as $item) {
            $filePath = $extractPath . '/' . $item['full-path'];
            
            if (file_exists($filePath)) {
                $html = file_get_contents($filePath);
                
                // Extract text from HTML
                $text = $this->htmlToText($html);
                
                if (!empty(trim($text))) {
                    $content[] = [
                        'id'      => $item['id'],
                        'href'    => $item['href'],
                        'title'   => $this->extractChapterTitle($html),
                        'content' => $text,
                    ];
                }
            }
        }
        
        return $content;
    }

    /**
     * Convert HTML to plain text
     * 
     * @param string $html HTML content
     * @return string Plain text
     */
    private function htmlToText(string $html): string
    {
        // Remove script and style tags
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        
        // Replace common block elements with newlines
        $html = preg_replace('/<\/(p|div|h[1-6]|br)\s*>/i', "\n", $html);
        
        // Strip remaining tags
        $text = strip_tags($html);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        
        return trim($text);
    }

    /**
     * Extract chapter title from HTML
     * 
     * @param string $html HTML content
     * @return string|null Chapter title
     */
    private function extractChapterTitle(string $html): ?string
    {
        // Try to find h1-h6 tags
        if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $html, $matches)) {
            return strip_tags($matches[1]);
        }
        
        // Try to find title in EPUB title tag
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Initialize AI service
     * 
     * @param array $config Configuration
     * @return AiServiceInterface AI service instance
     * @throws \Exception If initialization fails
     */
    private function initializeAiService(array $config): AiServiceInterface
    {
        $provider = $config['ai_provider'];
        $model = $config['ai_model'];
        $apiKey = null;
        
        // Use custom API key if specified
        if (!empty($config['use_custom_key']) && !empty($config['custom_api_key'])) {
            $apiKey = $config['custom_api_key'];
        }
        
        return $this->aiFactory->create($provider, $model, $apiKey);
    }

    /**
     * Validate API key format
     * 
     * @param string $apiKey API key
     * @param string $provider Provider name
     * @return bool True if valid format
     */
    private function validateApiKey(string $apiKey, string $provider): bool
    {
        switch ($provider) {
            case 'openai':
                return strpos($apiKey, 'sk-') === 0 && strlen($apiKey) > 20;
                
            case 'claude':
                return strpos($apiKey, 'sk-ant-') === 0 && strlen($apiKey) > 20;
                
            case 'gemini':
                return strlen($apiKey) > 20;
                
            default:
                return strlen($apiKey) > 10;
        }
    }

    /**
     * Create output EPUB file
     * 
     * @param string $sourcePath Source EPUB extract path
     * @param array $script Manga script content
     * @param string $outputPath Output file path
     * @param array $config Configuration
     * @return void
     */
    private function createOutputEpub(string $sourcePath, array $script, string $outputPath, array $config): void
    {
        // Format script as HTML content
        $formattedContent = $this->formatter->formatAsHtml($script, $config);
        
        // Create new EPUB structure
        $outputDir = dirname($outputPath) . '/tmp_' . basename($outputPath, '.epub');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        // Copy base structure from source
        $this->copyDirectory($sourcePath, $outputDir);
        
        // Replace content with manga script
        $this->replaceContent($outputDir, $formattedContent);
        
        // Update metadata
        $this->updateMetadata($outputDir, $config);
        
        // Repack EPUB
        $this->parser->repack($outputDir, $outputPath);
        
        // Clean up temp directory
        $this->recursiveDelete($outputDir);
    }

    /**
     * Copy directory recursively
     * 
     * @param string $source Source directory
     * @param string $dest Destination directory
     * @return void
     */
    private function copyDirectory(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $destPath = $dest . '/' . $iterator->getSubPathName();
            
            if ($file->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($file->getPathname(), $destPath);
            }
        }
    }

    /**
     * Replace content in output EPUB
     * 
     * @param string $outputDir Output directory
     * @param array $formattedContent Formatted content
     * @return void
     */
    private function replaceContent(string $outputDir, array $formattedContent): void
    {
        // Find content files and replace with manga script
        $contentDir = $outputDir . '/OEBPS';
        if (!is_dir($contentDir)) {
            $contentDir = $outputDir;
        }
        
        // Create new content file with manga script
        $scriptHtml = $this->generateScriptHtml($formattedContent);
        
        // Find and replace main content files
        foreach (glob($contentDir . '/*.html') as $file) {
            if (strpos(basename($file), 'script') !== false) {
                continue; // Skip if already a script file
            }
            
            // For now, create a new script file alongside
            $scriptFile = dirname($file) . '/manga-script.xhtml';
            file_put_contents($scriptFile, $scriptHtml);
            break; // Only create one script file for now
        }
    }

    /**
     * Generate script HTML
     * 
     * @param array $content Formatted content
     * @return string HTML
     */
    private function generateScriptHtml(array $content): string
    {
        $html = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $html .= '<!DOCTYPE html>' . "\n";
        $html .= '<html xmlns="http://www.w3.org/1999/xhtml">' . "\n";
        $html .= '<head>' . "\n";
        $html .= '<title>Manga Script</title>' . "\n";
        $html .= '<link rel="stylesheet" type="text/css" href="manga-script.css"/>' . "\n";
        $html .= '</head>' . "\n";
        $html .= '<body>' . "\n";
        $html .= '<div class="manga-script">' . "\n";
        
        foreach ($content as $section) {
            $html .= '<div class="script-section">' . "\n";
            
            if (!empty($section['title'])) {
                $html .= '<h2 class="scene-title">' . htmlspecialchars($section['title']) . '</h2>' . "\n";
            }
            
            if (!empty($section['description'])) {
                $html .= '<div class="scene-description">' . $section['description'] . '</div>' . "\n";
            }
            
            foreach ($section['panels'] ?? [] as $panel) {
                $html .= '<div class="panel">' . "\n";
                $html .= '<div class="panel-number">Panel ' . ($panel['number'] ?? '1') . '</div>' . "\n";
                
                if (!empty($panel['description'])) {
                    $html .= '<div class="panel-description">' . $panel['description'] . '</div>' . "\n";
                }
                
                foreach ($panel['dialogue'] ?? [] as $dialogue) {
                    $html .= '<div class="dialogue">' . "\n";
                    $html .= '<span class="character">' . htmlspecialchars($dialogue['character']) . ':</span>' . "\n";
                    $html .= '<span class="line">' . htmlspecialchars($dialogue['line']) . '</span>' . "\n";
                    
                    if (!empty($dialogue['emotion'])) {
                        $html .= '<span class="emotion">(' . htmlspecialchars($dialogue['emotion']) . ')</span>' . "\n";
                    }
                    
                    $html .= '</div>' . "\n";
                }
                
                $html .= '</div>' . "\n";
            }
            
            $html .= '</div>' . "\n";
        }
        
        $html .= '</div>' . "\n";
        $html .= '</body>' . "\n";
        $html .= '</html>';
        
        return $html;
    }

    /**
     * Update EPUB metadata
     * 
     * @param string $outputDir Output directory
     * @param array $config Configuration
     * @return void
     */
    private function updateMetadata(string $outputDir, array $config): void
    {
        // Find OPF file
        $containerFile = $outputDir . '/META-INF/container.xml';
        if (!file_exists($containerFile)) {
            return;
        }
        
        $container = simplexml_load_file($containerFile);
        if ($container === false) {
            return;
        }
        
        $opfPath = (string) $container->rootfiles->rootfile['full-path'];
        $opfFile = $outputDir . '/' . $opfPath;
        
        if (!file_exists($opfFile)) {
            return;
        }
        
        $opf = simplexml_load_file($opfFile);
        if ($opf === false) {
            return;
        }
        
        // Update title
        $namespaces = $opf->metadata->getNamespaces(true);
        $dcNs = $namespaces['dc'] ?? 'http://purl.org/dc/elements/1.1/';
        
        if (isset($opf->metadata->children($dcNs)->title)) {
            $opf->metadata->children($dcNs)->title[0] = $opf->metadata->children($dcNs)->title[0] . ' (Manga Script)';
        }
        
        // Add modified date
        $modified = date('Y-m-d\TH:i:s\Z');
        $metaExists = false;
        
        foreach ($opf->metadata->meta as $meta) {
            if ((string) $meta['property'] === 'dcterms:modified') {
                $meta[0] = $modified;
                $metaExists = true;
                break;
            }
        }
        
        if (!$metaExists) {
            $newMeta = $opf->metadata->addChild('meta');
            $newMeta->addAttribute('property', 'dcterms:modified');
            $newMeta[0] = $modified;
        }
        
        // Save updated OPF
        $opf->asXML($opfFile);
    }

    /**
     * Validate output EPUB
     * 
     * @param string $outputPath Output file path
     * @return array Validation result
     */
    private function validateOutput(string $outputPath): array
    {
        $validator = new \DGLab\Tools\EpubFontChanger\EpubValidator();
        return $validator->validate($outputPath);
    }

    /**
     * Update job progress
     * 
     * @param string $jobId Job ID
     * @param int $progress Progress percentage
     * @param string $message Progress message
     * @return void
     */
    private function updateProgress(string $jobId, int $progress, string $message): void
    {
        if (isset($this->jobs[$jobId])) {
            $this->jobs[$jobId]['progress'] = $progress;
            $this->jobs[$jobId]['message'] = $message;
        }
    }

    /**
     * Generate unique job ID
     * 
     * @return string Job ID
     */
    private function generateJobId(): string
    {
        return 'manga_' . bin2hex(random_bytes(8));
    }

    /**
     * Recursively delete directory
     * 
     * @param string $dir Directory path
     * @return void
     */
    private function recursiveDelete(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    $path = $dir . '/' . $object;
                    if (is_dir($path)) {
                        $this->recursiveDelete($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
