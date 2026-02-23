<?php
/**
 * DGLab PWA - EPUB Parser
 * 
 * Parses and extracts EPUB file contents for processing.
 * 
 * @package DGLab\Tools\EpubFontChanger
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\EpubFontChanger;

/**
 * EpubParser Class
 * 
 * Handles EPUB file parsing, extraction, and repackaging.
 */
class EpubParser
{
    /**
     * @var \ZipArchive $zip ZipArchive instance
     */
    private ?\ZipArchive $zip = null;
    
    /**
     * @var string $mimetype EPUB mimetype
     */
    private string $mimetype = 'application/epub+zip';

    /**
     * Parse EPUB file and extract metadata
     * 
     * @param string $epubPath Path to EPUB file
     * @return array EPUB information
     */
    public function parse(string $epubPath): array
    {
        $info = [
            'valid'       => false,
            'version'     => null,
            'title'       => null,
            'author'      => null,
            'language'    => null,
            'identifier'  => null,
            'manifest'    => [],
            'spine'       => [],
            'css_files'   => [],
            'html_files'  => [],
            'font_files'  => [],
            'error'       => null,
        ];
        
        try {
            // Open EPUB as ZIP
            $this->zip = new \ZipArchive();
            
            if ($this->zip->open($epubPath) !== true) {
                $info['error'] = 'Failed to open EPUB file';
                return $info;
            }
            
            // Verify mimetype
            $mimetypeContent = $this->zip->getFromName('mimetype');
            if ($mimetypeContent === false || trim($mimetypeContent) !== $this->mimetype) {
                $info['error'] = 'Invalid mimetype';
                $this->zip->close();
                return $info;
            }
            
            // Find and parse container.xml
            $containerXml = $this->zip->getFromName('META-INF/container.xml');
            if ($containerXml === false) {
                $info['error'] = 'Missing META-INF/container.xml';
                $this->zip->close();
                return $info;
            }
            
            // Parse container.xml to find OPF path
            $container = simplexml_load_string($containerXml);
            if ($container === false) {
                $info['error'] = 'Failed to parse container.xml';
                $this->zip->close();
                return $info;
            }
            
            $opfPath = (string) $container->rootfiles->rootfile['full-path'];
            if (empty($opfPath)) {
                $info['error'] = 'Could not find OPF file path';
                $this->zip->close();
                return $info;
            }
            
            $info['opf_path'] = $opfPath;
            $info['opf_dir'] = dirname($opfPath);
            if ($info['opf_dir'] === '.') {
                $info['opf_dir'] = '';
            }
            
            // Parse OPF file
            $opfContent = $this->zip->getFromName($opfPath);
            if ($opfContent === false) {
                $info['error'] = 'Failed to read OPF file';
                $this->zip->close();
                return $info;
            }
            
            $opf = simplexml_load_string($opfContent);
            if ($opf === false) {
                $info['error'] = 'Failed to parse OPF file';
                $this->zip->close();
                return $info;
            }
            
            // Register namespaces
            $namespaces = $opf->getNamespaces(true);
            $dcNs = $namespaces['dc'] ?? 'http://purl.org/dc/elements/1.1/';
            
            // Get EPUB version
            $info['version'] = (string) ($opf['version'] ?? '2.0');
            
            // Extract metadata
            $metadata = $opf->metadata;
            if ($metadata) {
                $info['title'] = (string) ($metadata->children($dcNs)->title ?? 'Untitled');
                $info['author'] = (string) ($metadata->children($dcNs)->creator ?? 'Unknown');
                $info['language'] = (string) ($metadata->children($dcNs)->language ?? 'en');
                $info['identifier'] = (string) ($metadata->children($dcNs)->identifier ?? '');
            }
            
            // Parse manifest
            $manifest = $opf->manifest;
            if ($manifest) {
                foreach ($manifest->item as $item) {
                    $itemData = [
                        'id'        => (string) $item['id'],
                        'href'      => (string) $item['href'],
                        'media-type'=> (string) $item['media-type'],
                        'full-path' => $this->resolvePath($info['opf_dir'], (string) $item['href']),
                    ];
                    
                    $info['manifest'][$itemData['id']] = $itemData;
                    
                    // Categorize files
                    if (strpos($itemData['media-type'], 'text/css') !== false) {
                        $info['css_files'][] = $itemData;
                    } elseif (strpos($itemData['media-type'], 'html') !== false || 
                              strpos($itemData['media-type'], 'xhtml') !== false) {
                        $info['html_files'][] = $itemData;
                    } elseif (strpos($itemData['media-type'], 'font') !== false) {
                        $info['font_files'][] = $itemData;
                    }
                }
            }
            
            // Parse spine
            $spine = $opf->spine;
            if ($spine) {
                foreach ($spine->itemref as $itemref) {
                    $idref = (string) $itemref['idref'];
                    if (isset($info['manifest'][$idref])) {
                        $info['spine'][] = $info['manifest'][$idref];
                    }
                }
            }
            
            $info['valid'] = true;
            
            $this->zip->close();
            
        } catch (\Exception $e) {
            $info['error'] = $e->getMessage();
            if ($this->zip !== null) {
                $this->zip->close();
            }
        }
        
        return $info;
    }

    /**
     * Extract EPUB contents to directory
     * 
     * @param string $epubPath Path to EPUB file
     * @param string $extractPath Directory to extract to
     * @return bool True on success
     */
    public function extract(string $epubPath, string $extractPath): bool
    {
        $zip = new \ZipArchive();
        
        if ($zip->open($epubPath) !== true) {
            throw new \Exception('Failed to open EPUB file for extraction');
        }
        
        // Ensure extract directory exists
        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }
        
        // Extract all files
        $result = $zip->extractTo($extractPath);
        $zip->close();
        
        return $result;
    }

    /**
     * Repack extracted EPUB contents into EPUB file
     * 
     * @param string $sourcePath Source directory
     * @param string $outputPath Output EPUB file path
     * @return bool True on success
     */
    public function repack(string $sourcePath, string $outputPath): bool
    {
        $zip = new \ZipArchive();
        
        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Failed to create output EPUB file');
        }
        
        // Add mimetype first (must be uncompressed and first in archive)
        $mimetypePath = $sourcePath . '/mimetype';
        if (file_exists($mimetypePath)) {
            $zip->addFile($mimetypePath, 'mimetype');
            $zip->setCompressionName('mimetype', \ZipArchive::CM_STORE);
        }
        
        // Add all other files
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourcePath) + 1);
            
            // Skip mimetype (already added)
            if ($relativePath === 'mimetype') {
                continue;
            }
            
            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
        
        $result = $zip->close();
        
        return $result;
    }

    /**
     * Resolve relative path against base path
     * 
     * @param string $basePath Base directory
     * @param string $relativePath Relative path
     * @return string Resolved path
     */
    private function resolvePath(string $basePath, string $relativePath): string
    {
        if (empty($basePath)) {
            return $relativePath;
        }
        
        return $basePath . '/' . $relativePath;
    }

    /**
     * Get file content from EPUB
     * 
     * @param string $epubPath Path to EPUB file
     * @param string $internalPath Internal file path
     * @return string|null File content or null
     */
    public function getFile(string $epubPath, string $internalPath): ?string
    {
        $zip = new \ZipArchive();
        
        if ($zip->open($epubPath) !== true) {
            return null;
        }
        
        $content = $zip->getFromName($internalPath);
        $zip->close();
        
        return $content !== false ? $content : null;
    }

    /**
     * Update file content in extracted EPUB
     * 
     * @param string $extractPath Extracted EPUB directory
     * @param string $internalPath Internal file path
     * @param string $content New content
     * @return bool True on success
     */
    public function updateFile(string $extractPath, string $internalPath, string $content): bool
    {
        $filePath = $extractPath . '/' . $internalPath;
        $dir = dirname($filePath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return file_put_contents($filePath, $content) !== false;
    }
}
