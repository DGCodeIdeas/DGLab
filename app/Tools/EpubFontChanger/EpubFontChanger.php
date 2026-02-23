<?php
/**
 * DGLab PWA - EPUB Font Changer Tool
 * 
 * This tool allows users to change fonts in EPUB e-books while maintaining
 * valid EPUB 3 output. Features include:
 * - Font injection into EPUB files
 * - CSS modification for font-family declarations
 * - EPUB 3 validation
 * - Support for custom fonts and system fonts
 * - Font subsetting for smaller file sizes
 * 
 * @package DGLab\Tools\EpubFontChanger
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\EpubFontChanger;

use DGLab\Tools\Interfaces\ToolInterface;

/**
 * EpubFontChanger Class
 * 
 * Main tool class for changing fonts in EPUB files.
 */
class EpubFontChanger implements ToolInterface
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
     * @var array $jobs Active processing jobs
     */
    private array $jobs = [];
    
    /**
     * @var EpubParser $parser EPUB parser instance
     */
    private EpubParser $parser;
    
    /**
     * @var FontInjector $fontInjector Font injector instance
     */
    private FontInjector $fontInjector;
    
    /**
     * @var EpubValidator $validator EPUB validator instance
     */
    private EpubValidator $validator;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->tempPath = STORAGE_PATH . '/temp/epub';
        $this->exportsPath = EXPORTS_PATH . '/epub';
        
        // Ensure directories exist
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
        if (!is_dir($this->exportsPath)) {
            mkdir($this->exportsPath, 0755, true);
        }
        
        // Initialize components
        $this->parser = new EpubParser();
        $this->fontInjector = new FontInjector();
        $this->validator = new EpubValidator();
    }

    // =============================================================================
    // TOOL INTERFACE IMPLEMENTATION
    // =============================================================================

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'epub-font-changer';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'EPUB Font Changer';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Change fonts in EPUB e-books while maintaining valid EPUB 3 output. ' .
               'Supports custom fonts, system fonts, and font subsetting for optimal file sizes.';
    }

    /**
     * {@inheritdoc}
     */
    public function getIcon(): string
    {
        return 'fa-book';
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
        return 100 * 1024 * 1024; // 100MB
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
            'font_family' => [
                'type'        => 'string',
                'label'       => 'Font Family',
                'description' => 'Name of the font to use',
                'required'    => true,
                'default'     => 'Merriweather',
            ],
            'font_source' => [
                'type'        => 'select',
                'label'       => 'Font Source',
                'description' => 'Source of the font',
                'options'     => [
                    'system'    => 'System Font',
                    'google'    => 'Google Font',
                    'upload'    => 'Upload Custom Font',
                    'builtin'   => 'Built-in Font',
                ],
                'required'    => true,
                'default'     => 'google',
            ],
            'font_file' => [
                'type'        => 'file',
                'label'       => 'Font File',
                'description' => 'Upload a font file (TTF, OTF, WOFF, WOFF2)',
                'required'    => false,
                'accept'      => '.ttf,.otf,.woff,.woff2',
                'conditional' => ['font_source' => 'upload'],
            ],
            'font_weight' => [
                'type'        => 'select',
                'label'       => 'Font Weight',
                'description' => 'Default font weight',
                'options'     => [
                    '300' => 'Light (300)',
                    '400' => 'Regular (400)',
                    '500' => 'Medium (500)',
                    '600' => 'Semi-Bold (600)',
                    '700' => 'Bold (700)',
                ],
                'required'    => true,
                'default'     => '400',
            ],
            'font_size' => [
                'type'        => 'number',
                'label'       => 'Font Size',
                'description' => 'Base font size in pixels',
                'min'         => 8,
                'max'         => 32,
                'required'    => true,
                'default'     => 16,
            ],
            'line_height' => [
                'type'        => 'number',
                'label'       => 'Line Height',
                'description' => 'Line height multiplier',
                'min'         => 1.0,
                'max'         => 3.0,
                'step'        => 0.1,
                'required'    => true,
                'default'     => 1.6,
            ],
            'subset_font' => [
                'type'        => 'boolean',
                'label'       => 'Subset Font',
                'description' => 'Remove unused characters to reduce file size',
                'required'    => false,
                'default'     => true,
            ],
            'embed_font' => [
                'type'        => 'boolean',
                'label'       => 'Embed Font',
                'description' => 'Embed font files in the EPUB',
                'required'    => false,
                'default'     => true,
            ],
            'apply_to_headings' => [
                'type'        => 'boolean',
                'label'       => 'Apply to Headings',
                'description' => 'Also change font for headings (h1-h6)',
                'required'    => false,
                'default'     => true,
            ],
            'fallback_fonts' => [
                'type'        => 'string',
                'label'       => 'Fallback Fonts',
                'description' => 'Comma-separated list of fallback fonts',
                'required'    => false,
                'default'     => 'Georgia, Times New Roman, serif',
            ],
            'preserve_original' => [
                'type'        => 'boolean',
                'label'       => 'Preserve Original',
                'description' => 'Keep original font files as backup',
                'required'    => false,
                'default'     => false,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultConfig(): array
    {
        return [
            'font_family'       => 'Merriweather',
            'font_source'       => 'google',
            'font_weight'       => '400',
            'font_size'         => 16,
            'line_height'       => 1.6,
            'subset_font'       => true,
            'embed_font'        => true,
            'apply_to_headings' => true,
            'fallback_fonts'    => 'Georgia, Times New Roman, serif',
            'preserve_original' => false,
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
                        number_format($this->getMaxFileSize() / 1024 / 1024, 2) . ' MB';
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
                $errors[] = 'Invalid EPUB structure: ' . $epubInfo['error'];
            }
            
            // Check EPUB version
            if (isset($epubInfo['version']) && version_compare($epubInfo['version'], '3.0', '<')) {
                $errors[] = 'EPUB version ' . $epubInfo['version'] . ' detected. ' .
                            'EPUB 3.0 or higher is recommended for best compatibility.';
            }
            
        } catch (\Exception $e) {
            $errors[] = 'Failed to parse EPUB: ' . $e->getMessage();
        }
        
        // Validate font options
        if (!empty($options)) {
            $fontErrors = $this->validateFontOptions($options);
            $errors = array_merge($errors, $fontErrors);
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
                'id'        => $jobId,
                'status'    => 'processing',
                'progress'  => 0,
                'started'   => $startTime,
            ];
            
            // Create working directory
            $workDir = $this->tempPath . '/' . $jobId;
            if (!is_dir($workDir)) {
                mkdir($workDir, 0755, true);
            }
            
            $this->updateProgress($jobId, 5, 'Extracting EPUB...');
            
            // Extract EPUB
            $extractPath = $workDir . '/extracted';
            $this->parser->extract($inputPath, $extractPath);
            
            $this->updateProgress($jobId, 20, 'Parsing EPUB structure...');
            
            // Parse EPUB info
            $epubInfo = $this->parser->parse($inputPath);
            
            $this->updateProgress($jobId, 30, 'Preparing fonts...');
            
            // Prepare fonts
            $fontFiles = $this->fontInjector->prepareFonts($config, $extractPath, $epubInfo);
            
            $this->updateProgress($jobId, 50, 'Injecting fonts...');
            
            // Inject fonts into EPUB
            $this->fontInjector->inject($fontFiles, $extractPath, $epubInfo, $config);
            
            $this->updateProgress($jobId, 70, 'Updating CSS...');
            
            // Update CSS files
            $this->fontInjector->updateCss($extractPath, $config);
            
            $this->updateProgress($jobId, 85, 'Repacking EPUB...');
            
            // Generate output filename
            $originalName = pathinfo($inputPath, PATHINFO_FILENAME);
            $fontName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $config['font_family']);
            $outputName = $originalName . '_' . $fontName . '.epub';
            $outputPath = $this->exportsPath . '/' . $outputName;
            
            // Repack EPUB
            $this->parser->repack($extractPath, $outputPath);
            
            $this->updateProgress($jobId, 95, 'Validating output...');
            
            // Validate output
            $validation = $this->validator->validate($outputPath);
            
            if (!$validation['valid']) {
                throw new \Exception('EPUB validation failed: ' . implode(', ', $validation['errors']));
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
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function cleanup(?string $jobId = null): void
    {
        if ($jobId !== null) {
            // Clean up specific job
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
    // PRIVATE METHODS
    // =============================================================================

    /**
     * Validate font options
     * 
     * @param array $options Font options
     * @return array Validation errors
     */
    private function validateFontOptions(array $options): array
    {
        $errors = [];
        
        // Validate font source
        $validSources = ['system', 'google', 'upload', 'builtin'];
        if (isset($options['font_source']) && !in_array($options['font_source'], $validSources, true)) {
            $errors[] = 'Invalid font source';
        }
        
        // Validate font size
        if (isset($options['font_size'])) {
            $size = (int) $options['font_size'];
            if ($size < 8 || $size > 32) {
                $errors[] = 'Font size must be between 8 and 32 pixels';
            }
        }
        
        // Validate line height
        if (isset($options['line_height'])) {
            $height = (float) $options['line_height'];
            if ($height < 1.0 || $height > 3.0) {
                $errors[] = 'Line height must be between 1.0 and 3.0';
            }
        }
        
        return $errors;
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
        return 'epub_' . bin2hex(random_bytes(8));
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

    // =============================================================================
    // ADDITIONAL PUBLIC METHODS
    // =============================================================================

    /**
     * Get list of available Google Fonts
     * 
     * @return array List of font names
     */
    public function getAvailableGoogleFonts(): array
    {
        return [
            'Merriweather'    => 'Merriweather (Serif)',
            'Lora'            => 'Lora (Serif)',
            'Playfair Display' => 'Playfair Display (Serif)',
            'Crimson Text'    => 'Crimson Text (Serif)',
            'Libre Baskerville' => 'Libre Baskerville (Serif)',
            'Open Sans'       => 'Open Sans (Sans-serif)',
            'Roboto'          => 'Roboto (Sans-serif)',
            'Lato'            => 'Lato (Sans-serif)',
            'Source Sans Pro' => 'Source Sans Pro (Sans-serif)',
            'Noto Sans'       => 'Noto Sans (Sans-serif)',
            'Fira Code'       => 'Fira Code (Monospace)',
            'Source Code Pro' => 'Source Code Pro (Monospace)',
            'JetBrains Mono'  => 'JetBrains Mono (Monospace)',
        ];
    }

    /**
     * Get list of available system fonts
     * 
     * @return array List of font names
     */
    public function getAvailableSystemFonts(): array
    {
        return [
            'Georgia'         => 'Georgia (Serif)',
            'Times New Roman' => 'Times New Roman (Serif)',
            'Palatino'        => 'Palatino (Serif)',
            'Garamond'        => 'Garamond (Serif)',
            'Arial'           => 'Arial (Sans-serif)',
            'Helvetica'       => 'Helvetica (Sans-serif)',
            'Verdana'         => 'Verdana (Sans-serif)',
            'Trebuchet MS'    => 'Trebuchet MS (Sans-serif)',
            'Courier New'     => 'Courier New (Monospace)',
            'Consolas'        => 'Consolas (Monospace)',
        ];
    }

    /**
     * Get list of built-in fonts
     * 
     * @return array List of font info
     */
    public function getBuiltinFonts(): array
    {
        $fontsPath = ASSETS_PATH . '/fonts';
        $fonts = [];
        
        if (is_dir($fontsPath)) {
            $files = glob($fontsPath . '/*.{ttf,otf,woff,woff2}', GLOB_BRACE);
            foreach ($files as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $fonts[$name] = [
                    'name' => $name,
                    'file' => basename($file),
                    'path' => $file,
                ];
            }
        }
        
        return $fonts;
    }
}
