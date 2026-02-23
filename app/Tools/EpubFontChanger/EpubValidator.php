<?php
/**
 * DGLab PWA - EPUB Validator
 * 
 * Validates EPUB files for EPUB 3 compliance.
 * 
 * @package DGLab\Tools\EpubFontChanger
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\EpubFontChanger;

/**
 * EpubValidator Class
 * 
 * Validates EPUB structure and content for EPUB 3 compliance.
 */
class EpubValidator
{
    /**
     * @var array $errors Validation errors
     */
    private array $errors = [];
    
    /**
     * @var array $warnings Validation warnings
     */
    private array $warnings = [];
    
    /**
     * @var array $requiredFiles Files required in EPUB
     */
    private array $requiredFiles = [
        'mimetype',
        'META-INF/container.xml',
    ];
    
    /**
     * @var array $validMimetypes Valid mimetype values
     */
    private array $validMimetypes = [
        'application/epub+zip',
    ];

    /**
     * Validate EPUB file
     * 
     * @param string $epubPath Path to EPUB file
     * @return array Validation result
     */
    public function validate(string $epubPath): array
    {
        $this->errors = [];
        $this->warnings = [];
        
        // Check file exists
        if (!file_exists($epubPath)) {
            return [
                'valid'    => false,
                'errors'   => ['File not found'],
                'warnings' => [],
            ];
        }
        
        // Open as ZIP
        $zip = new \ZipArchive();
        
        if ($zip->open($epubPath) !== true) {
            return [
                'valid'    => false,
                'errors'   => ['Failed to open EPUB as ZIP archive'],
                'warnings' => [],
            ];
        }
        
        // Validate structure
        $this->validateStructure($zip);
        $this->validateMimetype($zip);
        $this->validateContainer($zip);
        $this->validateOpf($zip);
        
        $zip->close();
        
        return [
            'valid'    => empty($this->errors),
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Validate basic EPUB structure
     * 
     * @param \ZipArchive $zip ZIP archive
     * @return void
     */
    private function validateStructure(\ZipArchive $zip): void
    {
        // Check required files exist
        foreach ($this->requiredFiles as $file) {
            if ($zip->locateName($file) === false) {
                $this->errors[] = "Required file missing: {$file}";
            }
        }
        
        // Check for OEBPS or content directory
        $hasContent = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, 'OEBPS/') === 0 || strpos($name, 'content/') === 0) {
                $hasContent = true;
                break;
            }
        }
        
        if (!$hasContent) {
            $this->warnings[] = 'No standard content directory found (OEBPS or content)';
        }
    }

    /**
     * Validate mimetype file
     * 
     * @param \ZipArchive $zip ZIP archive
     * @return void
     */
    private function validateMimetype(\ZipArchive $zip): void
    {
        $mimetypeContent = $zip->getFromName('mimetype');
        
        if ($mimetypeContent === false) {
            $this->errors[] = 'mimetype file not found';
            return;
        }
        
        $mimetype = trim($mimetypeContent);
        
        if (!in_array($mimetype, $this->validMimetypes, true)) {
            $this->errors[] = "Invalid mimetype: {$mimetype}";
        }
        
        // Check mimetype is uncompressed (first file in archive)
        $stat = $zip->statName('mimetype');
        if ($stat && $stat['comp_method'] !== 0) {
            $this->warnings[] = 'mimetype file should be uncompressed';
        }
    }

    /**
     * Validate META-INF/container.xml
     * 
     * @param \ZipArchive $zip ZIP archive
     * @return void
     */
    private function validateContainer(\ZipArchive $zip): void
    {
        $containerContent = $zip->getFromName('META-INF/container.xml');
        
        if ($containerContent === false) {
            $this->errors[] = 'META-INF/container.xml not found';
            return;
        }
        
        // Parse XML
        libxml_use_internal_errors(true);
        $container = simplexml_load_string($containerContent);
        
        if ($container === false) {
            $this->errors[] = 'Failed to parse META-INF/container.xml';
            return;
        }
        
        // Check rootfiles
        if (!isset($container->rootfiles->rootfile)) {
            $this->errors[] = 'No rootfile found in container.xml';
            return;
        }
        
        // Check each rootfile
        foreach ($container->rootfiles->rootfile as $rootfile) {
            $fullPath = (string) $rootfile['full-path'];
            $mediaType = (string) $rootfile['media-type'];
            
            if (empty($fullPath)) {
                $this->errors[] = 'Rootfile missing full-path attribute';
                continue;
            }
            
            if ($mediaType !== 'application/oebps-package+xml') {
                $this->errors[] = "Invalid rootfile media-type: {$mediaType}";
            }
            
            // Check OPF file exists
            if ($zip->locateName($fullPath) === false) {
                $this->errors[] = "OPF file not found: {$fullPath}";
            }
        }
    }

    /**
     * Validate OPF package file
     * 
     * @param \ZipArchive $zip ZIP archive
     * @return void
     */
    private function validateOpf(\ZipArchive $zip): void
    {
        // Find OPF path from container
        $containerContent = $zip->getFromName('META-INF/container.xml');
        $container = simplexml_load_string($containerContent);
        
        if ($container === false) {
            return;
        }
        
        $opfPath = (string) $container->rootfiles->rootfile['full-path'];
        
        if (empty($opfPath)) {
            return;
        }
        
        $opfContent = $zip->getFromName($opfPath);
        
        if ($opfContent === false) {
            return;
        }
        
        // Parse OPF
        libxml_use_internal_errors(true);
        $opf = simplexml_load_string($opfContent);
        
        if ($opf === false) {
            $this->errors[] = 'Failed to parse OPF file';
            return;
        }
        
        // Check package version
        $version = (string) ($opf['version'] ?? '2.0');
        
        if (version_compare($version, '3.0', '<')) {
            $this->warnings[] = 'EPUB version ' . $version . ' detected. EPUB 3.0+ recommended.';
        }
        
        // Validate metadata
        $this->validateMetadata($opf);
        
        // Validate manifest
        $this->validateManifest($opf, $zip, dirname($opfPath));
        
        // Validate spine
        $this->validateSpine($opf);
    }

    /**
     * Validate OPF metadata
     * 
     * @param \SimpleXMLElement $opf OPF XML
     * @return void
     */
    private function validateMetadata(\SimpleXMLElement $opf): void
    {
        $metadata = $opf->metadata;
        
        if ($metadata === null) {
            $this->errors[] = 'Missing metadata section in OPF';
            return;
        }
        
        // Register DC namespace
        $namespaces = $metadata->getNamespaces(true);
        $dcNs = $namespaces['dc'] ?? 'http://purl.org/dc/elements/1.1/';
        
        // Check required metadata elements
        $dcElements = $metadata->children($dcNs);
        
        // Title is required
        if (!isset($dcElements->title) || empty(trim((string) $dcElements->title))) {
            $this->errors[] = 'Missing or empty dc:title in metadata';
        }
        
        // Identifier is required
        if (!isset($dcElements->identifier) || empty(trim((string) $dcElements->identifier))) {
            $this->errors[] = 'Missing or empty dc:identifier in metadata';
        }
        
        // Language is required
        if (!isset($dcElements->language) || empty(trim((string) $dcElements->language))) {
            $this->errors[] = 'Missing or empty dc:language in metadata';
        }
        
        // Check for modified date (EPUB 3)
        $version = (string) ($opf['version'] ?? '2.0');
        if (version_compare($version, '3.0', '>=')) {
            $metaElements = $metadata->meta;
            $hasModified = false;
            
            foreach ($metaElements as $meta) {
                if ((string) $meta['property'] === 'dcterms:modified') {
                    $hasModified = true;
                    break;
                }
            }
            
            if (!$hasModified) {
                $this->warnings[] = 'EPUB 3 should have dcterms:modified meta element';
            }
        }
    }

    /**
     * Validate OPF manifest
     * 
     * @param \SimpleXMLElement $opf OPF XML
     * @param \ZipArchive $zip ZIP archive
     * @param string $opfDir OPF directory
     * @return void
     */
    private function validateManifest(\SimpleXMLElement $opf, \ZipArchive $zip, string $opfDir): void
    {
        $manifest = $opf->manifest;
        
        if ($manifest === null) {
            $this->errors[] = 'Missing manifest section in OPF';
            return;
        }
        
        $itemIds = [];
        $itemHrefs = [];
        
        foreach ($manifest->item as $item) {
            $id = (string) $item['id'];
            $href = (string) $item['href'];
            $mediaType = (string) $item['media-type'];
            
            // Check ID uniqueness
            if (in_array($id, $itemIds, true)) {
                $this->errors[] = "Duplicate manifest item ID: {$id}";
            }
            $itemIds[] = $id;
            
            // Check href uniqueness
            if (in_array($href, $itemHrefs, true)) {
                $this->errors[] = "Duplicate manifest item href: {$href}";
            }
            $itemHrefs[] = $href;
            
            // Check file exists
            $fullPath = empty($opfDir) || $opfDir === '.' ? $href : $opfDir . '/' . $href;
            if ($zip->locateName($fullPath) === false) {
                $this->errors[] = "Manifest item not found: {$href}";
            }
            
            // Check media-type is present
            if (empty($mediaType)) {
                $this->errors[] = "Manifest item missing media-type: {$id}";
            }
        }
        
        // Check for NCX (EPUB 2) or NAV (EPUB 3)
        $version = (string) ($opf['version'] ?? '2.0');
        $hasNcx = false;
        $hasNav = false;
        
        foreach ($manifest->item as $item) {
            $mediaType = (string) $item['media-type'];
            if ($mediaType === 'application/x-dtbncx+xml') {
                $hasNcx = true;
            }
            if ($mediaType === 'application/xhtml+xml' && (string) $item['properties'] === 'nav') {
                $hasNav = true;
            }
        }
        
        if (version_compare($version, '3.0', '>=')) {
            if (!$hasNav) {
                $this->errors[] = 'EPUB 3 requires a nav document in manifest';
            }
        } else {
            if (!$hasNcx) {
                $this->warnings[] = 'EPUB 2 should have an NCX file';
            }
        }
    }

    /**
     * Validate OPF spine
     * 
     * @param \SimpleXMLElement $opf OPF XML
     * @return void
     */
    private function validateSpine(\SimpleXMLElement $opf): void
    {
        $spine = $opf->spine;
        
        if ($spine === null) {
            $this->errors[] = 'Missing spine section in OPF';
            return;
        }
        
        // Check toc attribute (EPUB 2)
        $version = (string) ($opf['version'] ?? '2.0');
        if (version_compare($version, '3.0', '<')) {
            $toc = (string) ($spine['toc'] ?? '');
            if (empty($toc)) {
                $this->warnings[] = 'EPUB 2 spine should have toc attribute';
            }
        }
        
        // Check itemrefs
        $itemrefs = $spine->itemref;
        if (count($itemrefs) === 0) {
            $this->errors[] = 'Spine contains no itemrefs';
            return;
        }
        
        foreach ($itemrefs as $itemref) {
            $idref = (string) ($itemref['idref'] ?? '');
            
            if (empty($idref)) {
                $this->errors[] = 'Spine itemref missing idref attribute';
            }
        }
    }

    /**
     * Quick validation check
     * 
     * @param string $epubPath Path to EPUB file
     * @return bool True if valid
     */
    public function isValid(string $epubPath): bool
    {
        $result = $this->validate($epubPath);
        return $result['valid'];
    }

    /**
     * Get detailed validation report
     * 
     * @param string $epubPath Path to EPUB file
     * @return string Formatted report
     */
    public function getReport(string $epubPath): string
    {
        $result = $this->validate($epubPath);
        
        $report = "EPUB Validation Report\n";
        $report .= "======================\n\n";
        $report .= "Status: " . ($result['valid'] ? 'VALID' : 'INVALID') . "\n\n";
        
        if (!empty($result['errors'])) {
            $report .= "Errors:\n";
            foreach ($result['errors'] as $error) {
                $report .= "  - {$error}\n";
            }
            $report .= "\n";
        }
        
        if (!empty($result['warnings'])) {
            $report .= "Warnings:\n";
            foreach ($result['warnings'] as $warning) {
                $report .= "  - {$warning}\n";
            }
        }
        
        return $report;
    }
}
