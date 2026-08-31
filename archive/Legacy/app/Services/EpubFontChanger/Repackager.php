<?php

/**
 * DGLab EPUB Repackager
 *
 * Repackages EPUB files with proper ZIP structure.
 * Ensures EPUB 3.x compliance for output files.
 *
 * @package DGLab\Services\EpubFontChanger
 */

namespace DGLab\Services\EpubFontChanger;

/**
 * Class Repackager
 *
 * EPUB repackager providing:
 * - Proper ZIP structure (mimetype first, uncompressed)
 * - Manifest updates
 * - Progress callbacks
 * - Validation
 */
class Repackager
{
    /**
     * Source directory
     */
    private string $sourcePath;

    /**
     * Output file path
     */
    private string $outputPath;

    /**
     * New files to add
     */
    private array $newFiles = [];

    /**
     * Manifest updates
     */
    private array $manifestUpdates = [];

    /**
     * Progress callback
     * @var callable|null
     */
    private $progressCallback = null;

    /**
     * Set source directory
     */
    public function setSource(string $sourcePath): self
    {
        $this->sourcePath = $sourcePath;

        return $this;
    }

    /**
     * Set output file
     */
    public function setOutput(string $outputPath): self
    {
        $this->outputPath = $outputPath;

        return $this;
    }

    /**
     * Add a new file
     */
    public function addFile(string $localPath, string $archivePath): self
    {
        $this->newFiles[] = [
            'local' => $localPath,
            'archive' => $archivePath,
        ];

        return $this;
    }

    /**
     * Update manifest with new items
     */
    public function updateManifest(array $newItems): self
    {
        $this->manifestUpdates = array_merge($this->manifestUpdates, $newItems);

        return $this;
    }

    /**
     * Set progress callback
     */
    public function setProgressCallback(callable $callback): self
    {
        $this->progressCallback = $callback;

        return $this;
    }

    /**
     * Report progress
     */
    private function reportProgress(int $percent, ?string $message = null): void
    {
        if ($this->progressCallback !== null) {
            ($this->progressCallback)($percent, $message);
        }
    }

    /**
     * Create the EPUB package
     */
    public function create(): bool
    {
        if (!is_dir($this->sourcePath)) {
            throw new \RuntimeException('Source directory not found: ' . $this->sourcePath);
        }

        // Ensure output directory exists
        $outputDir = dirname($this->outputPath);

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Remove existing output file
        if (file_exists($this->outputPath)) {
            unlink($this->outputPath);
        }

        // Create ZIP archive
        $zip = new \ZipArchive();

        if ($zip->open($this->outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create ZIP archive');
        }

        // Add mimetype first (uncompressed, no extra fields)
        $this->addMimetype($zip);

        $this->reportProgress(5, 'Added mimetype');

        // Add META-INF
        $this->addDirectory($zip, $this->sourcePath . '/META-INF', 'META-INF');

        $this->reportProgress(15, 'Added META-INF');

        // Add all other files
        $this->addAllFiles($zip);

        $this->reportProgress(80, 'Added content files');

        // Add new files
        foreach ($this->newFiles as $file) {
            if (file_exists($file['local'])) {
                $zip->addFile($file['local'], $file['archive']);
            }
        }

        $this->reportProgress(90, 'Added new files');

        // Update OPF manifest if needed
        if (!empty($this->manifestUpdates)) {
            $this->updateOpfManifest($zip);
        }

        $this->reportProgress(95, 'Updated manifest');

        // Close ZIP
        $zip->close();

        $this->reportProgress(100, 'EPUB created successfully');

        return true;
    }

    /**
     * Add mimetype file (must be first and uncompressed)
     */
    private function addMimetype(\ZipArchive $zip): void
    {
        $mimetypePath = $this->sourcePath . '/mimetype';

        if (!file_exists($mimetypePath)) {
            // Create mimetype
            $mimetypePath = sys_get_temp_dir() . '/mimetype_' . uniqid();
            file_put_contents($mimetypePath, 'application/epub+zip');
        }

        // Add with no compression
        $zip->addFile($mimetypePath, 'mimetype');
        $zip->setCompressionName('mimetype', \ZipArchive::CM_STORE);
    }

    /**
     * Add a directory to the ZIP
     */
    private function addDirectory(\ZipArchive $zip, string $localPath, string $archivePath): void
    {
        if (!is_dir($localPath)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $archivePath . '/' . substr($filePath, strlen($localPath) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    /**
     * Add all files from source directory
     */
    private function addAllFiles(\ZipArchive $zip): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $totalFiles = iterator_count($files);
        $processedFiles = 0;

        // Reset iterator
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($this->sourcePath) + 1);

            // Skip mimetype (already added)
            if ($relativePath === 'mimetype') {
                continue;
            }

            // Skip files we're replacing
            foreach ($this->newFiles as $newFile) {
                if ($newFile['archive'] === $relativePath) {
                    continue 2;
                }
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }

            $processedFiles++;

            if ($processedFiles % 10 === 0) {
                $progress = 15 + (int) (($processedFiles / $totalFiles) * 65);
                $this->reportProgress($progress, "Added {$processedFiles}/{$totalFiles} files");
            }
        }
    }

    /**
     * Update OPF manifest with new items
     */
    private function updateOpfManifest(\ZipArchive $zip): void
    {
        // Find OPF file
        $opfPath = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (preg_match('/\.opf$/i', $name)) {
                $opfPath = $name;
                break;
            }
        }

        if ($opfPath === null) {
            return;
        }

        // Read OPF
        $opfContent = $zip->getFromName($opfPath);

        if ($opfContent === false) {
            return;
        }

        // Parse OPF
        $opf = simplexml_load_string($opfContent);

        if ($opf === false) {
            return;
        }

        // Register namespace
        $opf->registerXPathNamespace('opf', 'http://www.idpf.org/2007/opf');

        // Find manifest
        $manifest = $opf->manifest;

        if ($manifest === null) {
            return;
        }

        // Add new items
        foreach ($this->manifestUpdates as $item) {
            $newItem = $manifest->addChild('item');
            $newItem['id'] = $item['id'];
            $newItem['href'] = $item['href'];
            $newItem['media-type'] = $item['media-type'];

            if (isset($item['properties'])) {
                $newItem['properties'] = $item['properties'];
            }
        }

        // Update modification date
        $metadata = $opf->metadata;

        if ($metadata instanceof \SimpleXMLElement) {
            $metadata->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');

            $meta = $metadata->addChild('meta', gmdate('Y-m-d\TH:i:s\Z'));
            $meta->addAttribute('property', 'dcterms:modified');
        }

        // Save updated OPF
        $updatedOpf = $opf->asXML();

        // Remove old OPF and add updated one
        $zip->deleteName($opfPath);
        $zip->addFromString($opfPath, $updatedOpf);
    }

    /**
     * Validate the output EPUB
     */
    public function validateOutput(): bool
    {
        if (!file_exists($this->outputPath)) {
            return false;
        }

        $zip = new \ZipArchive();

        if ($zip->open($this->outputPath) !== true) {
            return false;
        }

        // Check mimetype is first file
        if ($zip->getNameIndex(0) !== 'mimetype') {
            $zip->close();
            return false;
        }

        // Check mimetype content
        $mimetype = $zip->getFromName('mimetype');

        if (trim($mimetype) !== 'application/epub+zip') {
            $zip->close();
            return false;
        }

        // Check META-INF/container.xml exists
        if ($zip->locateName('META-INF/container.xml') === false) {
            $zip->close();
            return false;
        }

        $zip->close();

        return true;
    }

    /**
     * Get output file size
     */
    public function getOutputSize(): int
    {
        if (!file_exists($this->outputPath)) {
            return 0;
        }

        return filesize($this->outputPath);
    }

    /**
     * Clean up source directory
     */
    public function cleanup(): void
    {
        if (is_dir($this->sourcePath)) {
            $this->recursiveDelete($this->sourcePath);
        }
    }

    /**
     * Recursively delete directory
     */
    private function recursiveDelete(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
