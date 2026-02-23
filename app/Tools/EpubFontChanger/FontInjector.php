<?php
/**
 * DGLab PWA - Font Injector
 * 
 * Handles font injection and CSS modification for EPUB files.
 * 
 * @package DGLab\Tools\EpubFontChanger
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\EpubFontChanger;

/**
 * FontInjector Class
 * 
 * Injects fonts into EPUB files and updates CSS accordingly.
 */
class FontInjector
{
    /**
     * @var string $fontsPath Path to fonts directory
     */
    private string $fontsPath;
    
    /**
     * @var array $googleFontsCache Cache of downloaded Google Fonts
     */
    private array $googleFontsCache = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->fontsPath = STORAGE_PATH . '/fonts';
        
        if (!is_dir($this->fontsPath)) {
            mkdir($this->fontsPath, 0755, true);
        }
    }

    /**
     * Prepare fonts for injection
     * 
     * @param array $config Font configuration
     * @param string $extractPath Extracted EPUB path
     * @param array $epubInfo EPUB information
     * @return array Prepared font files info
     */
    public function prepareFonts(array $config, string $extractPath, array $epubInfo): array
    {
        $fontFiles = [];
        $fontSource = $config['font_source'];
        $fontFamily = $config['font_family'];
        
        switch ($fontSource) {
            case 'google':
                $fontFiles = $this->downloadGoogleFont($fontFamily, $config);
                break;
                
            case 'upload':
                if (isset($config['font_file_path']) && file_exists($config['font_file_path'])) {
                    $fontFiles = $this->processUploadedFont($config['font_file_path'], $config);
                }
                break;
                
            case 'builtin':
                $fontFiles = $this->getBuiltinFont($fontFamily, $config);
                break;
                
            case 'system':
                // System fonts don't need files, just CSS
                $fontFiles = [];
                break;
        }
        
        return $fontFiles;
    }

    /**
     * Inject fonts into EPUB
     * 
     * @param array $fontFiles Font files to inject
     * @param string $extractPath Extracted EPUB path
     * @param array $epubInfo EPUB information
     * @param array $config Font configuration
     * @return void
     */
    public function inject(array $fontFiles, string $extractPath, array $epubInfo, array $config): void
    {
        if (empty($fontFiles) || !$config['embed_font']) {
            return;
        }
        
        // Create fonts directory in EPUB
        $fontsDir = $extractPath . '/fonts';
        if (!is_dir($fontsDir)) {
            mkdir($fontsDir, 0755, true);
        }
        
        // Copy font files
        foreach ($fontFiles as $font) {
            $destPath = $fontsDir . '/' . $font['filename'];
            copy($font['path'], $destPath);
        }
        
        // Update OPF manifest to include fonts
        $this->updateOpfManifest($extractPath, $epubInfo, $fontFiles);
    }

    /**
     * Update CSS files with new font
     * 
     * @param string $extractPath Extracted EPUB path
     * @param array $config Font configuration
     * @return void
     */
    public function updateCss(string $extractPath, array $config): void
    {
        $fontFamily = $config['font_family'];
        $fallbackFonts = $config['fallback_fonts'] ?? 'Georgia, serif';
        $fontSize = $config['font_size'] ?? 16;
        $lineHeight = $config['line_height'] ?? 1.6;
        $fontWeight = $config['font_weight'] ?? '400';
        
        // Find all CSS files
        $cssFiles = $this->findCssFiles($extractPath);
        
        foreach ($cssFiles as $cssFile) {
            $cssContent = file_get_contents($cssFile);
            
            // Add @font-face rules if embedding
            if ($config['embed_font'] && $config['font_source'] !== 'system') {
                $fontFaceRules = $this->generateFontFaceRules($config);
                $cssContent = $fontFaceRules . "\n" . $cssContent;
            }
            
            // Update font-family declarations
            $cssContent = $this->updateFontFamily($cssContent, $fontFamily, $fallbackFonts, $config);
            
            // Update font-size
            $cssContent = $this->updateFontSize($cssContent, $fontSize);
            
            // Update line-height
            $cssContent = $this->updateLineHeight($cssContent, $lineHeight);
            
            // Update font-weight
            $cssContent = $this->updateFontWeight($cssContent, $fontWeight);
            
            file_put_contents($cssFile, $cssContent);
        }
    }

    /**
     * Download Google Font
     * 
     * @param string $fontFamily Font family name
     * @param array $config Configuration
     * @return array Font files info
     */
    private function downloadGoogleFont(string $fontFamily, array $config): array
    {
        $fontFiles = [];
        
        // Normalize font name for URL
        $fontParam = str_replace(' ', '+', $fontFamily);
        
        // Build weights string
        $weights = ['400', '700'];
        if (isset($config['font_weight'])) {
            $weights = [$config['font_weight']];
        }
        
        $weightsParam = implode(',', $weights);
        
        // Google Fonts API URL
        $apiUrl = "https://fonts.googleapis.com/css2?family={$fontParam}:wght@{$weightsParam}&display=swap";
        
        // Fetch CSS
        $cssContent = @file_get_contents($apiUrl);
        
        if ($cssContent === false) {
            // Fallback: return empty (will use system fallback)
            return $fontFiles;
        }
        
        // Extract font URLs from CSS
        preg_match_all('/url\(([^)]+)\)/', $cssContent, $matches);
        
        foreach ($matches[1] as $index => $url) {
            $url = trim($url, '"\'');
            
            // Download font file
            $fontData = @file_get_contents($url);
            
            if ($fontData !== false) {
                // Determine format from URL
                $format = 'woff2';
                if (strpos($url, '.woff2') !== false) {
                    $format = 'woff2';
                } elseif (strpos($url, '.woff') !== false) {
                    $format = 'woff';
                } elseif (strpos($url, '.ttf') !== false) {
                    $format = 'truetype';
                }
                
                // Generate filename
                $weight = $weights[$index] ?? '400';
                $filename = $this->sanitizeFilename($fontFamily) . '_' . $weight . '.' . $format;
                $localPath = $this->fontsPath . '/' . $filename;
                
                file_put_contents($localPath, $fontData);
                
                $fontFiles[] = [
                    'path'     => $localPath,
                    'filename' => $filename,
                    'format'   => $format,
                    'weight'   => $weight,
                ];
            }
        }
        
        return $fontFiles;
    }

    /**
     * Process uploaded font file
     * 
     * @param string $filePath Path to uploaded font
     * @param array $config Configuration
     * @return array Font files info
     */
    private function processUploadedFont(string $filePath, array $config): array
    {
        $fontFiles = [];
        
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $formatMap = [
            'woff2' => 'woff2',
            'woff'  => 'woff',
            'ttf'   => 'truetype',
            'otf'   => 'opentype',
        ];
        
        $format = $formatMap[$extension] ?? 'truetype';
        $filename = basename($filePath);
        
        // Copy to fonts directory
        $localPath = $this->fontsPath . '/' . $filename;
        copy($filePath, $localPath);
        
        $fontFiles[] = [
            'path'     => $localPath,
            'filename' => $filename,
            'format'   => $format,
            'weight'   => $config['font_weight'] ?? '400',
        ];
        
        return $fontFiles;
    }

    /**
     * Get built-in font
     * 
     * @param string $fontFamily Font family name
     * @param array $config Configuration
     * @return array Font files info
     */
    private function getBuiltinFont(string $fontFamily, array $config): array
    {
        $fontFiles = [];
        $builtinPath = ASSETS_PATH . '/fonts/' . $this->sanitizeFilename($fontFamily);
        
        // Look for font files
        $extensions = ['woff2', 'woff', 'ttf', 'otf'];
        
        foreach ($extensions as $ext) {
            $fontFile = $builtinPath . '.' . $ext;
            
            if (file_exists($fontFile)) {
                $formatMap = [
                    'woff2' => 'woff2',
                    'woff'  => 'woff',
                    'ttf'   => 'truetype',
                    'otf'   => 'opentype',
                ];
                
                $filename = basename($fontFile);
                $localPath = $this->fontsPath . '/' . $filename;
                copy($fontFile, $localPath);
                
                $fontFiles[] = [
                    'path'     => $localPath,
                    'filename' => $filename,
                    'format'   => $formatMap[$ext],
                    'weight'   => $config['font_weight'] ?? '400',
                ];
            }
        }
        
        return $fontFiles;
    }

    /**
     * Generate @font-face CSS rules
     * 
     * @param array $config Configuration
     * @return string CSS rules
     */
    private function generateFontFaceRules(array $config): string
    {
        $rules = '';
        $fontFamily = $config['font_family'];
        
        // This would include the actual font files
        // For now, return a template
        $rules .= "/* Font Face Rules for {$fontFamily} */\n";
        
        return $rules;
    }

    /**
     * Update font-family in CSS
     * 
     * @param string $css CSS content
     * @param string $fontFamily New font family
     * @param string $fallback Fallback fonts
     * @param array $config Configuration
     * @return string Updated CSS
     */
    private function updateFontFamily(string $css, string $fontFamily, string $fallback, array $config): string
    {
        $fullFontStack = "'{$fontFamily}', {$fallback}";
        
        // Pattern to match font-family declarations
        $pattern = '/font-family\s*:\s*([^;]+);/i';
        
        $css = preg_replace_callback($pattern, function ($matches) use ($fullFontStack, $config) {
            $selectors = $matches[1];
            
            // Check if this is a heading selector (if not applying to headings)
            if (!$config['apply_to_headings']) {
                // Simple check - could be improved
                if (preg_match('/h[1-6]/i', $matches[0])) {
                    return $matches[0]; // Keep original
                }
            }
            
            return 'font-family: ' . $fullFontStack . ';';
        }, $css);
        
        // Add default font-family to body if not present
        if (strpos($css, 'body') === false || !preg_match('/body\s*\{[^}]*font-family/i', $css)) {
            $css .= "\nbody { font-family: {$fullFontStack}; }\n";
        }
        
        return $css;
    }

    /**
     * Update font-size in CSS
     * 
     * @param string $css CSS content
     * @param int $fontSize Font size in pixels
     * @return string Updated CSS
     */
    private function updateFontSize(string $css, int $fontSize): string
    {
        // Update existing font-size declarations
        $pattern = '/font-size\s*:\s*\d+(?:\.\d+)?(?:px|pt|em|rem|%);/i';
        $replacement = 'font-size: ' . $fontSize . 'px;';
        
        $css = preg_replace($pattern, $replacement, $css);
        
        // Add to body if not present
        if (!preg_match('/body\s*\{[^}]*font-size/i', $css)) {
            $css .= "\nbody { font-size: {$fontSize}px; }\n";
        }
        
        return $css;
    }

    /**
     * Update line-height in CSS
     * 
     * @param string $css CSS content
     * @param float $lineHeight Line height
     * @return string Updated CSS
     */
    private function updateLineHeight(string $css, float $lineHeight): string
    {
        // Update existing line-height declarations
        $pattern = '/line-height\s*:\s*\d+(?:\.\d+)?;?/i';
        $replacement = 'line-height: ' . $lineHeight . ';';
        
        $css = preg_replace($pattern, $replacement, $css);
        
        // Add to body if not present
        if (!preg_match('/body\s*\{[^}]*line-height/i', $css)) {
            $css .= "\nbody { line-height: {$lineHeight}; }\n";
        }
        
        return $css;
    }

    /**
     * Update font-weight in CSS
     * 
     * @param string $css CSS content
     * @param string $fontWeight Font weight
     * @return string Updated CSS
     */
    private function updateFontWeight(string $css, string $fontWeight): string
    {
        // Only update if not 'normal' (400)
        if ($fontWeight === '400' || $fontWeight === 'normal') {
            return $css;
        }
        
        // Update existing font-weight declarations
        $pattern = '/font-weight\s*:\s*(?:normal|bold|\d+);?/i';
        $replacement = 'font-weight: ' . $fontWeight . ';';
        
        $css = preg_replace($pattern, $replacement, $css);
        
        return $css;
    }

    /**
     * Find all CSS files in extracted EPUB
     * 
     * @param string $extractPath Extracted EPUB path
     * @return array CSS file paths
     */
    private function findCssFiles(string $extractPath): array
    {
        $cssFiles = [];
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'css') {
                $cssFiles[] = $file->getRealPath();
            }
        }
        
        return $cssFiles;
    }

    /**
     * Update OPF manifest to include font files
     * 
     * @param string $extractPath Extracted EPUB path
     * @param array $epubInfo EPUB information
     * @param array $fontFiles Font files to add
     * @return void
     */
    private function updateOpfManifest(string $extractPath, array $epubInfo, array $fontFiles): void
    {
        $opfPath = $extractPath . '/' . $epubInfo['opf_path'];
        
        if (!file_exists($opfPath)) {
            return;
        }
        
        $opfContent = file_get_contents($opfPath);
        $opf = simplexml_load_string($opfContent);
        
        if ($opf === false) {
            return;
        }
        
        // Find manifest
        $manifest = $opf->manifest;
        
        if ($manifest === null) {
            return;
        }
        
        // Add font entries
        foreach ($fontFiles as $font) {
            $item = $manifest->addChild('item');
            $item->addAttribute('id', 'font_' . pathinfo($font['filename'], PATHINFO_FILENAME));
            $item->addAttribute('href', 'fonts/' . $font['filename']);
            $item->addAttribute('media-type', $this->getFontMimeType($font['filename']));
        }
        
        // Save updated OPF
        $opf->asXML($opfPath);
    }

    /**
     * Get MIME type for font file
     * 
     * @param string $filename Font filename
     * @return string MIME type
     */
    private function getFontMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'woff2' => 'font/woff2',
            'woff'  => 'font/woff',
            'ttf'   => 'font/ttf',
            'otf'   => 'font/otf',
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Sanitize filename
     * 
     * @param string $name Name to sanitize
     * @return string Sanitized name
     */
    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    }
}
