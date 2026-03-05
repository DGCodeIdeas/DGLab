<?php
/**
 * DGLab Font Injection Engine
 * 
 * Generates and injects @font-face CSS into EPUB documents.
 * 
 * @package DGLab\Services\EpubFontChanger
 */

namespace DGLab\Services\EpubFontChanger;

/**
 * Class FontInjector
 * 
 * Font injection engine providing:
 * - @font-face CSS generation
 * - Base64 encoding for embedding
 * - CSS specificity management
 * - Multi-format support
 */
class FontInjector
{
    /**
     * Font formats and their MIME types
     */
    private const FONT_FORMATS = [
        'woff2' => ['mime' => 'font/woff2', 'format' => 'woff2'],
        'woff' => ['mime' => 'font/woff', 'format' => 'woff'],
        'ttf' => ['mime' => 'font/ttf', 'format' => 'truetype'],
        'otf' => ['mime' => 'font/otf', 'format' => 'opentype'],
    ];

    /**
     * Generate @font-face CSS from font files
     */
    public function generateFontFaceCSS(array $fonts, array $options = []): string
    {
        $useBase64 = $options['embed_base64'] ?? false;
        $fontPath = $options['font_path'] ?? 'fonts/';
        $fontDisplay = $options['font_display'] ?? 'swap';
        
        $css = '';
        
        foreach ($fonts as $font) {
            $family = $font['family'];
            $style = $font['style'] ?? 'normal';
            $weight = $font['weight'] ?? '400';
            $files = $font['files'] ?? [];
            
            $css .= "@font-face {\n";
            $css .= "  font-family: '{$family}';\n";
            $css .= "  font-style: {$style};\n";
            $css .= "  font-weight: {$weight};\n";
            $css .= "  font-display: {$fontDisplay};\n";
            
            // Build src list
            $srcList = [];
            
            foreach (['woff2', 'woff', 'ttf', 'otf'] as $format) {
                if (isset($files[$format])) {
                    if ($useBase64) {
                        $data = base64_encode(file_get_contents($files[$format]));
                        $mime = self::FONT_FORMATS[$format]['mime'];
                        $srcList[] = "url('data:{$mime};base64,{$data}') format('" . self::FONT_FORMATS[$format]['format'] . "')";
                    } else {
                        $filename = basename($files[$format]);
                        $srcList[] = "url('{$fontPath}{$filename}') format('" . self::FONT_FORMATS[$format]['format'] . "')";
                    }
                }
            }
            
            if (!empty($srcList)) {
                $css .= "  src: " . implode(',\n       ', $srcList) . ";\n";
            }
            
            $css .= "}\n\n";
        }
        
        return $css;
    }

    /**
     * Inject CSS into a stylesheet
     */
    public function injectIntoStylesheet(string $cssPath, string $fontCSS): void
    {
        $existing = '';
        
        if (file_exists($cssPath)) {
            $existing = file_get_contents($cssPath);
        }
        
        // Prepend font CSS at the beginning
        $newContent = $fontCSS . "\n" . $existing;
        
        file_put_contents($cssPath, $newContent);
    }

    /**
     * Update font-family rules in CSS
     */
    public function updateFontFamilyRules(string $cssPath, array $targetElements, string $fontFamily): void
    {
        if (!file_exists($cssPath)) {
            return;
        }
        
        $css = file_get_contents($cssPath);
        
        // Generate new rules
        $newRules = '';
        
        foreach ($targetElements as $selector => $description) {
            // Check if rule already exists
            $pattern = '/(' . preg_quote($selector, '/') . '\s*\{[^}]*)font-family:[^;]*;/i';
            
            if (preg_match($pattern, $css)) {
                // Update existing rule
                $css = preg_replace(
                    $pattern,
                    '$1font-family: \'' . $fontFamily . '\', serif;',
                    $css
                );
            } else {
                // Add new rule
                $newRules .= "{$selector} { font-family: '{$fontFamily}', serif; }\n";
            }
        }
        
        // Append new rules at the end
        if ($newRules) {
            $css .= "\n/* DGLab Font Injection */\n" . $newRules;
        }
        
        file_put_contents($cssPath, $css);
    }

    /**
     * Create a new font stylesheet
     */
    public function createFontStylesheet(string $outputPath, array $fonts, array $options = []): void
    {
        $fontCSS = $this->generateFontFaceCSS($fonts, $options);
        
        // Add font-family rules
        $targetElements = $options['target_elements'] ?? [
            'body' => 'Body text',
            'h1' => 'Heading 1',
            'h2' => 'Heading 2',
            'h3' => 'Heading 3',
            'h4' => 'Heading 4',
            'h5' => 'Heading 5',
            'h6' => 'Heading 6',
        ];
        
        if (!empty($targetElements) && isset($fonts[0]['family'])) {
            $fontFamily = $fonts[0]['family'];
            
            foreach ($targetElements as $selector => $description) {
                $fontCSS .= "{$selector} { font-family: '{$fontFamily}', serif; }\n";
            }
        }
        
        file_put_contents($outputPath, $fontCSS);
    }

    /**
     * Update HTML to link new CSS
     */
    public function updateHtmlCssLink(string $htmlPath, string $cssHref): void
    {
        if (!file_exists($htmlPath)) {
            return;
        }
        
        $html = file_get_contents($htmlPath);
        
        // Check if link already exists
        if (strpos($html, $cssHref) !== false) {
            return;
        }
        
        // Find </head> tag
        $headEndPos = stripos($html, '</head>');
        
        if ($headEndPos === false) {
            // Try to find <body> tag
            $bodyPos = stripos($html, '<body>');
            
            if ($bodyPos === false) {
                // Prepend to beginning
                $html = '<link rel="stylesheet" type="text/css" href="' . $cssHref . '"/>' . "\n" . $html;
            } else {
                // Insert before body
                $html = substr($html, 0, $bodyPos) . 
                        '<link rel="stylesheet" type="text/css" href="' . $cssHref . '"/>' . "\n" .
                        substr($html, $bodyPos);
            }
        } else {
            // Insert before </head>
            $html = substr($html, 0, $headEndPos) . 
                    '  <link rel="stylesheet" type="text/css" href="' . $cssHref . '"/>' . "\n" .
                    substr($html, $headEndPos);
        }
        
        file_put_contents($htmlPath, $html);
    }

    /**
     * Inject CSS directly into HTML
     */
    public function injectCssIntoHtml(string $htmlPath, string $css): void
    {
        if (!file_exists($htmlPath)) {
            return;
        }
        
        $html = file_get_contents($htmlPath);
        
        // Find </head> tag
        $headEndPos = stripos($html, '</head>');
        
        $styleTag = "\n<style type=\"text/css\">\n" . $css . "</style>\n";
        
        if ($headEndPos === false) {
            // Prepend to beginning
            $html = $styleTag . $html;
        } else {
            // Insert before </head>
            $html = substr($html, 0, $headEndPos) . $styleTag . substr($html, $headEndPos);
        }
        
        file_put_contents($htmlPath, $html);
    }

    /**
     * Copy font files to destination
     */
    public function copyFontFiles(array $fonts, string $destination): array
    {
        $copied = [];
        
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        foreach ($fonts as $font) {
            $files = $font['files'] ?? [];
            
            foreach ($files as $format => $sourcePath) {
                if (file_exists($sourcePath)) {
                    $filename = basename($sourcePath);
                    $destPath = $destination . '/' . $filename;
                    
                    copy($sourcePath, $destPath);
                    $copied[] = $destPath;
                }
            }
        }
        
        return $copied;
    }

    /**
     * Get font info from file
     */
    public function getFontInfo(string $fontPath): array
    {
        $info = [
            'path' => $fontPath,
            'filename' => basename($fontPath),
            'extension' => strtolower(pathinfo($fontPath, PATHINFO_EXTENSION)),
            'size' => filesize($fontPath),
        ];
        
        // Try to extract more info if possible
        // This is a simplified version - could be enhanced with font parsing libraries
        
        return $info;
    }

    /**
     * Generate font CSS for specific elements only
     */
    public function generateElementFontCSS(array $elements, string $fontFamily): string
    {
        $css = '';
        
        foreach ($elements as $selector) {
            $css .= "{$selector} { font-family: '{$fontFamily}', serif; }\n";
        }
        
        return $css;
    }

    /**
     * Add fallback fonts to CSS
     */
    public function addFallbackFonts(string $css, array $fallbacks): string
    {
        $fallbackList = implode(', ', array_map(function ($f) {
            return strpos($f, ' ') !== false ? "'{$f}'" : $f;
        }, $fallbacks));
        
        // Replace font-family declarations to add fallbacks
        $css = preg_replace(
            '/(font-family:\s*\'[^\']+\')/',
            '$1, ' . $fallbackList,
            $css
        );
        
        return $css;
    }
}
