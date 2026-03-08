<?php

/**
 * DGLab EPUB Font Changer Service
 *
 * Main service implementation for changing fonts in EPUB files.
 *
 * @package DGLab\Services\EpubFontChanger
 */

namespace DGLab\Services\EpubFontChanger;

use DGLab\Core\Application;
use DGLab\Core\Exceptions\ValidationException;
use DGLab\Database\UploadChunk;
use DGLab\Services\BaseService;
use DGLab\Services\Contracts\ChunkedServiceInterface;

/**
 * Class EpubFontChanger
 *
 * EPUB Font Changer service providing:
 * - Font injection into EPUB files
 * - Multiple font family support
 * - Chunked upload for large files
 * - Progress tracking
 */
class EpubFontChanger extends BaseService implements ChunkedServiceInterface
{
    /**
     * Service ID
     */
    private const SERVICE_ID = 'epub-font-changer';

    /**
     * Service name
     */
    private const SERVICE_NAME = 'EPUB Font Changer';

    /**
     * Service description
     */
    private const SERVICE_DESCRIPTION = 'Change fonts in EPUB e-books with open-source font families ' .
                                        'including OpenDyslexic, Merriweather, and Fira Sans.';

    /**
     * Service icon
     */
    private const SERVICE_ICON = 'bi bi-book';

    /**
     * EPUB Parser
     */
    private ?EpubParser $parser = null;

    /**
     * Font Injector
     */
    private ?FontInjector $injector = null;

    /**
     * Repackager
     */
    private ?Repackager $repackager = null;


    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->parser = new EpubParser();
        $this->injector = new FontInjector();
        $this->repackager = new Repackager();
    }

    /**
     * Get service ID
     */
    public function getId(): string
    {
        return self::SERVICE_ID;
    }

    /**
     * Get service name
     */
    public function getName(): string
    {
        return self::SERVICE_NAME;
    }

    /**
     * Get service description
     */
    public function getDescription(): string
    {
        return self::SERVICE_DESCRIPTION;
    }

    /**
     * Get service icon
     */
    public function getIcon(): string
    {
        return self::SERVICE_ICON;
    }

    /**
     * Get input schema
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'file' => [
                    'type' => 'string',
                    'format' => 'binary',
                    'description' => 'EPUB file to process',
                ],
                'font' => [
                    'type' => 'string',
                    'enum' => ['opendyslexic', 'merriweather', 'fira-sans'],
                    'default' => 'merriweather',
                    'description' => 'Font family to use',
                ],
                'target_elements' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => ['body', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre'],
                    ],
                    'default' => ['body'],
                    'description' => 'HTML elements to apply font to',
                ],
                'embed_base64' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Embed fonts as base64 in CSS',
                ],
            ],
            'required' => ['file'],
        ];
    }

    /**
     * Validate input
     */
    public function validate(array $input): array
    {
        return $this->validateAgainstSchema($input, $this->getInputSchema());
    }

    /**
     * Process the service request
     */
    public function process(array $input, ?callable $progressCallback = null): array
    {
        $this->reportProgress($progressCallback, 0, 'Starting EPUB font change');

        // Get file path
        $filePath = $input['file'];

        if (!file_exists($filePath)) {
            throw new \RuntimeException('EPUB file not found');
        }

        // Validate file type
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension !== 'epub') {
            throw new ValidationException(['file' => 'File must be an EPUB']);
        }

        // Get options
        $fontId = $input['font'] ?? 'merriweather';
        $targetElements = $input['target_elements'] ?? ['body'];
        $embedBase64 = $input['embed_base64'] ?? false;

        $this->reportProgress($progressCallback, 5, 'Extracting EPUB');

        // Extract EPUB
        $extractPath = $this->createTempDir('epub_extract');

        if (!$this->parser->load($filePath, $extractPath)) {
            throw new \RuntimeException('Failed to parse EPUB: ' . implode(', ', $this->parser->getErrors()));
        }

        $this->reportProgress($progressCallback, 20, 'EPUB extracted');

        // Validate EPUB
        if (!$this->parser->isValid()) {
            throw new \RuntimeException('Invalid EPUB: ' . implode(', ', $this->parser->getErrors()));
        }

        $this->reportProgress($progressCallback, 25, 'EPUB validated');

        // Get font configuration
        $fontConfig = $this->getFontConfig($fontId);

        if ($fontConfig === null) {
            throw new \RuntimeException('Unknown font: ' . $fontId);
        }

        $this->reportProgress($progressCallback, 30, 'Preparing font assets');

        // Prepare fonts
        $fontsPath = $extractPath . '/fonts';
        mkdir($fontsPath, 0755, true);

        $fonts = $this->prepareFonts($fontConfig, $fontsPath);

        $this->reportProgress($progressCallback, 40, 'Fonts prepared');

        // Generate font CSS
        $this->reportProgress($progressCallback, 50, 'Generating CSS');

        $fontCSS = $this->injector->generateFontFaceCSS($fonts, [
            'font_path' => 'fonts/',
            'embed_base64' => $embedBase64,
        ]);

        // Add element rules
        $elementSelectors = array_map(function ($e) {
            return $e === 'body' ? 'body, p' : $e;
        }, $targetElements);

        foreach ($elementSelectors as $selector) {
            $fontCSS .= "{$selector} { font-family: '" . $fontConfig['family'] . "', serif; }\n";
        }

        $this->reportProgress($progressCallback, 60, 'Injecting CSS');

        // Inject CSS into HTML files
        $htmlFiles = $this->parser->getHtmlFiles();

        foreach ($htmlFiles as $htmlFile) {
            $this->injector->injectCssIntoHtml($htmlFile['full-path'], $fontCSS);
        }

        $this->reportProgress($progressCallback, 70, 'CSS injected');

        // Update OPF manifest
        $opfDir = $this->parser->getOpfDir();
        $fontPrefix = $opfDir ? $opfDir . '/fonts/' : 'fonts/';

        $manifestUpdates = [];

        foreach ($fonts as $font) {
            foreach ($font['files'] as $format => $filePath) {
                $filename = basename($filePath);
                $mediaType = $this->getFontMediaType($format);

                $manifestUpdates[] = [
                    'id' => 'font-' . $fontId . '-' . $format,
                    'href' => $fontPrefix . $filename,
                    'media-type' => $mediaType,
                ];
            }
        }

        $this->reportProgress($progressCallback, 80, 'Repackaging EPUB');

        // Repackage
        $outputDir = Application::getInstance()->getBasePath() . '/storage/uploads/temp';
        $outputName = $this->generateOutputFilename($filePath, $fontId);
        $outputPath = $outputDir . '/' . $outputName;

        $this->repackager
            ->setSource($extractPath)
            ->setOutput($outputPath)
            ->updateManifest($manifestUpdates)
            ->setProgressCallback(function ($percent, $message) use ($progressCallback) {
                $adjustedPercent = 80 + (int) ($percent * 0.15);
                $this->reportProgress($progressCallback, $adjustedPercent, $message);
            })
            ->create();

        $this->reportProgress($progressCallback, 95, 'Validating output');

        // Validate output
        if (!$this->repackager->validateOutput()) {
            throw new \RuntimeException('Output EPUB validation failed');
        }

        $this->reportProgress($progressCallback, 100, 'Complete');

        // Get metadata
        $metadata = $this->parser->getMetadata();

        // Cleanup
        $this->parser->cleanup();

        return [
            'success' => true,
            'download_url' => '/api/download/' . basename($outputPath),
            'output_path' => $outputPath,
            'filename' => $outputName,
            'file_size' => filesize($outputPath),
            'metadata' => [
                'title' => $this->parser->getTitle(),
                'author' => $this->parser->getAuthor(),
                'font_applied' => $fontConfig['name'],
            ],
        ];
    }

    /**
     * Check if chunked upload is supported
     */
    public function supportsChunking(): bool
    {
        return true;
    }

    /**
     * Estimate processing time
     */
    public function estimateTime(array $input): int
    {
        $fileSize = $input['file_size'] ?? 0;

        // Rough estimate: 1MB takes ~3 seconds
        return (int) max(5, ($fileSize / 1048576) * 3);
    }

    /**
     * Get service configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Initialize chunked process
     */
    public function initializeChunkedProcess(array $metadata): array
    {
        $session = UploadChunk::createSession(
            $this->getId(),
            $metadata['filename'] ?? 'unknown.epub',
            $metadata['file_size'] ?? 0,
            $this->getChunkSize(),
            $metadata
        );

        return [
            'session_id' => $session->session_id,
            'chunk_size' => $this->getChunkSize(),
            'total_chunks' => $session->total_chunks,
            'expires_at' => $session->expires_at,
            'status_url' => '/api/chunk/status/' . $session->session_id,
        ];
    }

    /**
     * Process a chunk
     */
    public function processChunk(string $sessionId, int $chunkIndex, string $chunkData): array
    {
        $session = UploadChunk::findBySessionId($sessionId);

        if ($session === null) {
            throw new \RuntimeException('Session not found');
        }

        if ($session->isExpired()) {
            throw new \RuntimeException('Session expired');
        }

        // Save chunk
        $chunkDir = Application::getInstance()->getBasePath() . '/storage/uploads/chunks/' . $sessionId;

        if (!is_dir($chunkDir)) {
            mkdir($chunkDir, 0755, true);
        }

        $chunkPath = $chunkDir . '/chunk_' . $chunkIndex;
        file_put_contents($chunkPath, $chunkData);

        // Record chunk
        $session->recordChunk($chunkIndex, $chunkPath);

        return [
            'success' => true,
            'progress' => $session->getProgress(),
            'received_chunks' => $session->received_chunks,
            'total_chunks' => $session->total_chunks,
            'missing_chunks' => $session->getMissingChunks(),
        ];
    }

    /**
     * Finalize chunked process
     */
    public function finalizeChunkedProcess(string $sessionId): array
    {
        $session = UploadChunk::findBySessionId($sessionId);

        if ($session === null) {
            throw new \RuntimeException('Session not found');
        }

        if (!$session->isComplete()) {
            throw new \RuntimeException('Upload incomplete');
        }

        // Reassemble file
        $tempDir = Application::getInstance()->getBasePath() . '/storage/uploads/temp';
        $outputPath = $tempDir . '/' . $session->filename;

        if (!$session->reassemble($outputPath)) {
            throw new \RuntimeException('Failed to reassemble file');
        }

        // Get metadata
        $metadata = $session->metadata ?? [];

        // Process the file
        $result = $this->process(array_merge($metadata, [
            'file' => $outputPath,
        ]));

        // Cleanup session
        $session->cleanupChunks();
        $session->markExpired();

        // Remove reassembled file
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }

        return $result;
    }

    /**
     * Cancel chunked process
     */
    public function cancelChunkedProcess(string $sessionId): bool
    {
        $session = UploadChunk::findBySessionId($sessionId);

        if ($session === null) {
            return false;
        }

        $session->markCancelled();

        return true;
    }

    /**
     * Get chunked status
     */
    public function getChunkedStatus(string $sessionId): array
    {
        $session = UploadChunk::findBySessionId($sessionId);

        if ($session === null) {
            throw new \RuntimeException('Session not found');
        }

        return [
            'status' => $session->status,
            'progress' => $session->getProgress(),
            'received_chunks' => $session->received_chunks,
            'total_chunks' => $session->total_chunks,
            'missing_chunks' => $session->getMissingChunks(),
            'expires_at' => $session->expires_at,
        ];
    }

    /**
     * Get chunk size
     */
    public function getChunkSize(): int
    {
        return 1048576; // 1MB
    }

    /**
     * Get max file size
     */
    public function getMaxFileSize(): int
    {
        return 104857600; // 100MB
    }

    /**
     * Check if chunk is valid
     */
    public function isChunkValid(string $sessionId, int $chunkIndex, string $chunkData): bool
    {
        $session = UploadChunk::findBySessionId($sessionId);

        if ($session === null) {
            return false;
        }

        // Check chunk index is valid
        if ($chunkIndex < 0 || $chunkIndex >= $session->total_chunks) {
            return false;
        }

        // Check chunk size
        $expectedSize = strlen($chunkData);
        $maxSize = $session->chunk_size * 1.1; // Allow 10% overhead

        if ($expectedSize > $maxSize) {
            return false;
        }

        return true;
    }

    /**
     * Get font configuration
     */
    private function getFontConfig(string $fontId): ?array
    {
        $fonts = $this->config['fonts'] ?? [];

        return $fonts[$fontId] ?? null;
    }

    /**
     * Prepare fonts for injection
     */
    private function prepareFonts(array $fontConfig, string $outputPath): array
    {
        $fontsPath = $this->config('default_fonts_path');
        $family = $fontConfig['family'];

        $fonts = [];

        // Regular
        if (isset($fontConfig['files']['regular'])) {
            $sourcePath = $fontsPath . '/' . $fontConfig['files']['regular'];
            $destPath = $outputPath . '/' . basename($sourcePath);

            if (file_exists($sourcePath)) {
                copy($sourcePath, $destPath);

                $fonts[] = [
                    'family' => $family,
                    'style' => 'normal',
                    'weight' => '400',
                    'files' => $this->getFontFiles($fontConfig, $fontsPath, $outputPath),
                ];
            }
        }

        return $fonts;
    }

    /**
     * Get all font files
     */
    private function getFontFiles(array $fontConfig, string $fontsPath, string $outputPath): array
    {
        $files = [];

        foreach ($fontConfig['files'] as $variant => $filename) {
            $sourcePath = $fontsPath . '/' . $filename;
            $destPath = $outputPath . '/' . basename($sourcePath);

            if (file_exists($sourcePath)) {
                copy($sourcePath, $destPath);

                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $files[$extension] = $destPath;
            }
        }

        return $files;
    }

    /**
     * Get font media type
     */
    private function getFontMediaType(string $format): string
    {
        $types = [
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
        ];

        return $types[$format] ?? 'application/octet-stream';
    }

    /**
     * Generate output filename
     */
    private function generateOutputFilename(string $inputPath, string $fontId): string
    {
        $basename = pathinfo($inputPath, PATHINFO_FILENAME);

        return $basename . '-' . $fontId . '.epub';
    }

    /**
     * Report progress
     */
    protected function reportProgress(?callable $callback, int $percent, ?string $message = null): void
    {
        if ($callback !== null) {
            $callback($percent, $message);
        }
    }
}
