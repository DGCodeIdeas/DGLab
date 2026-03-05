<?php
/**
 * DGLab EPUB Parser
 * 
 * Parses EPUB 3.0/3.2 container structure and extracts metadata.
 * 
 * @package DGLab\Services\EpubFontChanger
 */

namespace DGLab\Services\EpubFontChanger;

/**
 * Class EpubParser
 * 
 * EPUB parser providing:
 * - Container structure parsing
 * - OPF package parsing
 * - Manifest extraction
 * - Spine ordering
 * - Metadata extraction
 * - Validation
 */
class EpubParser
{
    /**
     * EPUB file path
     */
    private string $epubPath;
    
    /**
     * Extraction directory
     */
    private string $extractPath;
    
    /**
     * OPF directory (relative to extraction root)
     */
    private string $opfDir = '';
    
    /**
     * OPF data
     */
    private ?\SimpleXMLElement $opf = null;
    
    /**
     * Manifest items
     */
    private array $manifest = [];
    
    /**
     * Spine order
     */
    private array $spine = [];
    
    /**
     * Metadata
     */
    private array $metadata = [];
    
    /**
     * Validation errors
     */
    private array $errors = [];

    /**
     * Load and parse an EPUB file
     */
    public function load(string $epubPath, string $extractPath): bool
    {
        $this->epubPath = $epubPath;
        $this->extractPath = $extractPath;
        
        // Extract EPUB
        if (!$this->extract()) {
            return false;
        }
        
        // Parse container
        if (!$this->parseContainer()) {
            return false;
        }
        
        // Parse OPF
        if (!$this->parseOpf()) {
            return false;
        }
        
        // Extract manifest
        $this->extractManifest();
        
        // Extract spine
        $this->extractSpine();
        
        // Extract metadata
        $this->extractMetadata();
        
        return true;
    }

    /**
     * Extract EPUB ZIP archive
     */
    private function extract(): bool
    {
        if (!file_exists($this->epubPath)) {
            $this->errors[] = 'EPUB file not found';
            return false;
        }
        
        // Create extraction directory
        if (!is_dir($this->extractPath)) {
            mkdir($this->extractPath, 0755, true);
        }
        
        // Extract ZIP
        $zip = new \ZipArchive();
        
        if ($zip->open($this->epubPath) !== true) {
            $this->errors[] = 'Failed to open EPUB as ZIP archive';
            return false;
        }
        
        $zip->extractTo($this->extractPath);
        $zip->close();
        
        return true;
    }

    /**
     * Parse META-INF/container.xml
     */
    private function parseContainer(): bool
    {
        $containerPath = $this->extractPath . '/META-INF/container.xml';
        
        if (!file_exists($containerPath)) {
            $this->errors[] = 'META-INF/container.xml not found';
            return false;
        }
        
        $container = simplexml_load_file($containerPath);
        
        if ($container === false) {
            $this->errors[] = 'Failed to parse container.xml';
            return false;
        }
        
        // Register namespaces
        $container->registerXPathNamespace('container', 'urn:oasis:names:tc:opendocument:xmlns:container');
        
        // Get OPF path
        $rootfiles = $container->xpath('//container:rootfile[@media-type="application/oebps-package+xml"]');
        
        if (empty($rootfiles)) {
            $this->errors[] = 'No OPF rootfile found in container';
            return false;
        }
        
        $opfPath = (string) $rootfiles[0]['full-path'];
        
        // Store OPF directory
        $this->opfDir = dirname($opfPath);
        if ($this->opfDir === '.') {
            $this->opfDir = '';
        }
        
        $this->opfPath = $this->extractPath . '/' . $opfPath;
        
        return true;
    }

    /**
     * Parse OPF package file
     */
    private function parseOpf(): bool
    {
        if (!file_exists($this->opfPath)) {
            $this->errors[] = 'OPF file not found: ' . $this->opfPath;
            return false;
        }
        
        $this->opf = simplexml_load_file($this->opfPath);
        
        if ($this->opf === false) {
            $this->errors[] = 'Failed to parse OPF file';
            return false;
        }
        
        // Register namespaces
        $this->opf->registerXPathNamespace('opf', 'http://www.idpf.org/2007/opf');
        $this->opf->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        
        return true;
    }

    /**
     * Extract manifest items
     */
    private function extractManifest(): void
    {
        if ($this->opf === null) {
            return;
        }
        
        $manifest = $this->opf->manifest;
        
        if ($manifest === null) {
            return;
        }
        
        foreach ($manifest->item as $item) {
            $id = (string) $item['id'];
            $href = (string) $item['href'];
            $mediaType = (string) $item['media-type'];
            $properties = (string) ($item['properties'] ?? '');
            
            $this->manifest[$id] = [
                'id' => $id,
                'href' => $href,
                'media-type' => $mediaType,
                'properties' => $properties ? explode(' ', $properties) : [],
                'full-path' => $this->resolvePath($href),
            ];
        }
    }

    /**
     * Extract spine order
     */
    private function extractSpine(): void
    {
        if ($this->opf === null) {
            return;
        }
        
        $spine = $this->opf->spine;
        
        if ($spine === null) {
            return;
        }
        
        foreach ($spine->itemref as $itemref) {
            $idref = (string) $itemref['idref'];
            $linear = (string) ($itemref['linear'] ?? 'yes');
            
            $this->spine[] = [
                'idref' => $idref,
                'linear' => $linear === 'yes',
            ];
        }
    }

    /**
     * Extract metadata
     */
    private function extractMetadata(): void
    {
        if ($this->opf === null) {
            return;
        }
        
        $metadata = $this->opf->metadata;
        
        if ($metadata === null) {
            return;
        }
        
        // Dublin Core elements
        $dcElements = ['title', 'creator', 'subject', 'description', 'publisher', 'contributor', 'date', 'type', 'format', 'identifier', 'source', 'language', 'relation', 'coverage', 'rights'];
        
        foreach ($dcElements as $element) {
            $values = $metadata->xpath("//dc:{$element}");
            
            if (!empty($values)) {
                $this->metadata[$element] = array_map(function ($v) {
                    return (string) $v;
                }, $values);
            }
        }
        
        // Meta elements
        foreach ($metadata->meta as $meta) {
            $name = (string) ($meta['name'] ?? '');
            $content = (string) ($meta['content'] ?? '');
            $property = (string) ($meta['property'] ?? '');
            
            if ($name && $content) {
                $this->metadata['meta'][$name] = $content;
            } elseif ($property) {
                $this->metadata['meta'][$property] = (string) $meta;
            }
        }
    }

    /**
     * Resolve relative path against OPF directory
     */
    private function resolvePath(string $href): string
    {
        if ($this->opfDir === '') {
            return $this->extractPath . '/' . $href;
        }
        
        return $this->extractPath . '/' . $this->opfDir . '/' . $href;
    }

    /**
     * Get OPF path
     */
    public function getOpfPath(): string
    {
        return $this->opfPath;
    }

    /**
     * Get OPF directory
     */
    public function getOpfDir(): string
    {
        return $this->opfDir;
    }

    /**
     * Get manifest
     */
    public function getManifest(): array
    {
        return $this->manifest;
    }

    /**
     * Get manifest item by ID
     */
    public function getManifestItem(string $id): ?array
    {
        return $this->manifest[$id] ?? null;
    }

    /**
     * Get manifest items by media type
     */
    public function getManifestByMediaType(string $mediaType): array
    {
        return array_filter($this->manifest, function ($item) use ($mediaType) {
            return $item['media-type'] === $mediaType;
        });
    }

    /**
     * Get spine
     */
    public function getSpine(): array
    {
        return $this->spine;
    }

    /**
     * Get spine as document paths
     */
    public function getSpineDocuments(): array
    {
        $documents = [];
        
        foreach ($this->spine as $itemref) {
            $item = $this->getManifestItem($itemref['idref']);
            
            if ($item !== null) {
                $documents[] = $item;
            }
        }
        
        return $documents;
    }

    /**
     * Get metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get metadata value
     */
    public function getMetadataValue(string $key, ?string $default = null): ?string
    {
        $values = $this->metadata[$key] ?? [];
        
        return $values[0] ?? $default;
    }

    /**
     * Get title
     */
    public function getTitle(): ?string
    {
        return $this->getMetadataValue('title');
    }

    /**
     * Get author/creator
     */
    public function getAuthor(): ?string
    {
        return $this->getMetadataValue('creator');
    }

    /**
     * Get language
     */
    public function getLanguage(): ?string
    {
        return $this->getMetadataValue('language', 'en');
    }

    /**
     * Get identifier
     */
    public function getIdentifier(): ?string
    {
        return $this->getMetadataValue('identifier');
    }

    /**
     * Get extraction path
     */
    public function getExtractPath(): string
    {
        return $this->extractPath;
    }

    /**
     * Get CSS files from manifest
     */
    public function getCssFiles(): array
    {
        return $this->getManifestByMediaType('text/css');
    }

    /**
     * Get HTML/XHTML files from manifest
     */
    public function getHtmlFiles(): array
    {
        $html = $this->getManifestByMediaType('application/xhtml+xml');
        $html2 = $this->getManifestByMediaType('text/html');
        
        return array_merge($html, $html2);
    }

    /**
     * Get font files from manifest
     */
    public function getFontFiles(): array
    {
        $fonts = [];
        $fontTypes = ['application/vnd.ms-opentype', 'application/font-sfnt', 'font/woff', 'font/woff2', 'application/font-woff'];
        
        foreach ($fontTypes as $type) {
            $fonts = array_merge($fonts, $this->getManifestByMediaType($type));
        }
        
        return $fonts;
    }

    /**
     * Validate EPUB structure
     */
    public function isValid(): bool
    {
        $this->errors = [];
        
        // Check mimetype file exists and is correct
        $mimetypePath = $this->extractPath . '/mimetype';
        
        if (!file_exists($mimetypePath)) {
            $this->errors[] = 'mimetype file not found';
        } else {
            $mimetype = file_get_contents($mimetypePath);
            
            if (trim($mimetype) !== 'application/epub+zip') {
                $this->errors[] = 'Invalid mimetype: ' . trim($mimetype);
            }
        }
        
        // Check META-INF/container.xml
        if (!file_exists($this->extractPath . '/META-INF/container.xml')) {
            $this->errors[] = 'META-INF/container.xml not found';
        }
        
        // Check OPF exists
        if (!isset($this->opfPath) || !file_exists($this->opfPath)) {
            $this->errors[] = 'OPF package file not found';
        }
        
        // Check manifest has items
        if (empty($this->manifest)) {
            $this->errors[] = 'Manifest is empty';
        }
        
        // Check spine has items
        if (empty($this->spine)) {
            $this->errors[] = 'Spine is empty';
        }
        
        // Validate manifest items exist
        foreach ($this->manifest as $item) {
            if (!file_exists($item['full-path'])) {
                $this->errors[] = 'Manifest item not found: ' . $item['href'];
            }
        }
        
        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first content document (cover or first spine item)
     */
    public function getCoverDocument(): ?array
    {
        // Look for cover in manifest properties
        foreach ($this->manifest as $item) {
            if (in_array('cover-image', $item['properties'], true)) {
                return $item;
            }
        }
        
        // Return first spine document
        $spine = $this->getSpineDocuments();
        
        return $spine[0] ?? null;
    }

    /**
     * Clean up extraction directory
     */
    public function cleanup(): void
    {
        if (is_dir($this->extractPath)) {
            $this->recursiveDelete($this->extractPath);
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
