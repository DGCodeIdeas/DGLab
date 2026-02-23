<?php
/**
 * DGLab PWA - Asset Bundler Class
 * 
 * The AssetBundler class provides runtime asset compilation without
 * requiring Node.js build tools. Features:
 * - SCSS to CSS compilation
 * - JavaScript minification and bundling
 * - CSS minification
 * - Cache management
 * - Source map generation (optional)
 * 
 * This allows InfinityFree deployment without local build tools.
 * 
 * @package DGLab\Core
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Core;

/**
 * AssetBundler Class
 * 
 * Runtime asset compilation for SCSS and JavaScript.
 */
class AssetBundler
{
    /**
     * @var string $assetsPath Path to assets directory
     */
    private string $assetsPath;
    
    /**
     * @var string $cachePath Path to cache directory
     */
    private string $cachePath;
    
    /**
     * @var string $publicPath Path to public directory
     */
    private string $publicPath;
    
    /**
     * @var bool $minificationEnabled Whether to minify output
     */
    private bool $minificationEnabled;
    
    /**
     * @var bool $cacheEnabled Whether caching is enabled
     */
    private bool $cacheEnabled;
    
    /**
     * @var array $scssVariables SCSS variables to inject
     */
    private array $scssVariables = [];
    
    /**
     * @var array $bundledAssets Cache of bundled asset paths
     */
    private static array $bundledAssets = [];

    /**
     * Constructor
     * 
     * @param array $options Bundler options
     */
    public function __construct(array $options = [])
    {
        global $config;
        
        $this->assetsPath = $options['assets_path'] ?? ASSETS_PATH;
        $this->cachePath = $options['cache_path'] ?? CACHE_PATH . '/assets';
        $this->publicPath = $options['public_path'] ?? PUBLIC_PATH;
        $this->minificationEnabled = $options['minify'] ?? ($config['assets']['minify'] ?? true);
        $this->cacheEnabled = $options['cache'] ?? ($config['assets']['cache'] ?? true);
        
        // Ensure cache directory exists
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    // =============================================================================
    // CSS/SCSS METHODS
    // =============================================================================

    /**
     * Compile SCSS to CSS
     * 
     * @param string|array $files SCSS file(s) to compile
     * @param string $outputName Output filename
     * @return string Path to compiled CSS
     */
    public function compileScss($files, string $outputName = 'app.css'): string
    {
        $files = is_array($files) ? $files : [$files];
        
        // Check cache
        if ($this->cacheEnabled && $this->isCacheValid($files, $outputName)) {
            return $this->getCacheUrl($outputName);
        }
        
        // Combine all SCSS content
        $scssContent = '';
        foreach ($files as $file) {
            $filePath = $this->assetsPath . '/scss/' . $file;
            if (file_exists($filePath)) {
                $scssContent .= file_get_contents($filePath) . "\n";
            }
        }
        
        // Inject variables
        $scssContent = $this->injectScssVariables($scssContent);
        
        // Compile SCSS
        $cssContent = $this->processScss($scssContent);
        
        // Minify if enabled
        if ($this->minificationEnabled) {
            $cssContent = $this->minifyCss($cssContent);
        }
        
        // Save to cache
        $this->saveToCache($outputName, $cssContent);
        
        return $this->getCacheUrl($outputName);
    }

    /**
     * Process SCSS content (basic SCSS compilation)
     * 
     * Note: This is a simplified SCSS processor for InfinityFree compatibility.
     * For complex SCSS, consider using a pre-compiled CSS file.
     * 
     * @param string $scss SCSS content
     * @return string Compiled CSS
     */
    private function processScss(string $scss): string
    {
        $css = $scss;
        
        // Process variables (simple replacement)
        $variables = [];
        
        // Extract variable definitions
        preg_match_all('/\$([a-zA-Z0-9_-]+)\s*:\s*([^;]+);/', $css, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $variables[$match[1]] = trim($match[2]);
        }
        
        // Remove variable definitions
        $css = preg_replace('/\$[a-zA-Z0-9_-]+\s*:\s*[^;]+;\s*/', '', $css);
        
        // Replace variable usage
        foreach ($variables as $name => $value) {
            $css = str_replace('$' . $name, $value, $css);
        }
        
        // Process nested rules (basic support)
        $css = $this->processNestedRules($css);
        
        // Process imports
        $css = $this->processImports($css, 'scss');
        
        // Process mixins (basic support)
        $css = $this->processMixins($css);
        
        return $css;
    }

    /**
     * Process nested SCSS rules
     * 
     * @param string $scss SCSS content
     * @return string Processed CSS
     */
    private function processNestedRules(string $scss): string
    {
        // This is a simplified nested rule processor
        // For complex nesting, pre-compile SCSS files
        
        $lines = explode("\n", $scss);
        $result = [];
        $selectors = [];
        $indentLevel = 0;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Skip empty lines
            if (empty($trimmed)) {
                $result[] = '';
                continue;
            }
            
            // Check for opening brace
            if (strpos($trimmed, '{') !== false) {
                $selector = trim(substr($trimmed, 0, strpos($trimmed, '{')));
                
                if ($indentLevel > 0 && !empty($selectors)) {
                    // Nested selector
                    $parent = implode(' ', $selectors);
                    if (strpos($selector, '&') === 0) {
                        // Parent reference
                        $selector = str_replace('&', end($selectors), $selector);
                    } else {
                        $selector = $parent . ' ' . $selector;
                    }
                }
                
                $selectors[] = $selector;
                $indentLevel++;
                
                // Add selector to result
                $result[] = $selector . ' {';
                
            } elseif (strpos($trimmed, '}') !== false) {
                // Closing brace
                array_pop($selectors);
                $indentLevel = max(0, $indentLevel - 1);
                $result[] = '}';
                
            } else {
                // Property or other content
                $result[] = $line;
            }
        }
        
        return implode("\n", $result);
    }

    /**
     * Process SCSS mixins
     * 
     * @param string $scss SCSS content
     * @return string Processed CSS
     */
    private function processMixins(string $scss): string
    {
        // Extract mixin definitions
        $mixins = [];
        preg_match_all('/@mixin\s+([a-zA-Z0-9_-]+)\s*\(([^)]*)\)\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}/s', $scss, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $mixins[$match[1]] = [
                'params' => array_map('trim', explode(',', $match[2])),
                'body' => $match[3],
            ];
        }
        
        // Remove mixin definitions
        $scss = preg_replace('/@mixin\s+[a-zA-Z0-9_-]+\s*\([^)]*\)\s*\{[^}]+(?:\{[^}]*\}[^}]*)*\}/s', '', $scss);
        
        // Process @include directives
        $scss = preg_replace_callback(
            '/@include\s+([a-zA-Z0-9_-]+)\s*\(([^)]*)\);/',
            function ($match) use ($mixins) {
                $mixinName = $match[1];
                if (!isset($mixins[$mixinName])) {
                    return '';
                }
                
                $args = array_map('trim', explode(',', $match[2]));
                $body = $mixins[$mixinName]['body'];
                
                // Replace parameters
                foreach ($mixins[$mixinName]['params'] as $i => $param) {
                    $paramName = trim($param);
                    $defaultValue = '';
                    
                    if (strpos($paramName, ':') !== false) {
                        list($paramName, $defaultValue) = explode(':', $paramName, 2);
                        $paramName = trim($paramName);
                        $defaultValue = trim($defaultValue);
                    }
                    
                    $value = $args[$i] ?? $defaultValue;
                    $body = str_replace('$' . $paramName, $value, $body);
                }
                
                return $body;
            },
            $scss
        );
        
        return $scss;
    }

    /**
     * Inject SCSS variables
     * 
     * @param string $scss SCSS content
     * @return string SCSS with injected variables
     */
    private function injectScssVariables(string $scss): string
    {
        if (empty($this->scssVariables)) {
            return $scss;
        }
        
        $variables = '';
        foreach ($this->scssVariables as $name => $value) {
            $variables .= "\${$name}: {$value};\n";
        }
        
        return $variables . "\n" . $scss;
    }

    /**
     * Minify CSS
     * 
     * @param string $css CSS content
     * @return string Minified CSS
     */
    public function minifyCss(string $css): string
    {
        // Remove comments
        $css = preg_replace('/\/\*[^*]*\*+(?:[^\/][^*]*\*+)*\//', '', $css);
        
        // Remove whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove unnecessary spaces
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
        
        // Remove trailing semicolons
        $css = preg_replace('/;}/', '}', $css);
        
        // Trim
        return trim($css);
    }

    // =============================================================================
    // JAVASCRIPT METHODS
    // =============================================================================

    /**
     * Bundle JavaScript files
     * 
     * @param string|array $files JavaScript file(s) to bundle
     * @param string $outputName Output filename
     * @return string Path to bundled JS
     */
    public function bundleJs($files, string $outputName = 'app.js'): string
    {
        $files = is_array($files) ? $files : [$files];
        
        // Check cache
        if ($this->cacheEnabled && $this->isCacheValid($files, $outputName, 'js')) {
            return $this->getCacheUrl($outputName);
        }
        
        // Combine all JS content
        $jsContent = '';
        foreach ($files as $file) {
            $filePath = $this->assetsPath . '/js/' . $file;
            if (file_exists($filePath)) {
                $jsContent .= "/* {$file} */\n";
                $jsContent .= file_get_contents($filePath) . "\n\n";
            }
        }
        
        // Process imports
        $jsContent = $this->processImports($jsContent, 'js');
        
        // Minify if enabled
        if ($this->minificationEnabled) {
            $jsContent = $this->minifyJs($jsContent);
        }
        
        // Save to cache
        $this->saveToCache($outputName, $jsContent);
        
        return $this->getCacheUrl($outputName);
    }

    /**
     * Minify JavaScript
     * 
     * @param string $js JavaScript content
     * @return string Minified JavaScript
     */
    public function minifyJs(string $js): string
    {
        // Remove single-line comments
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Remove extra whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Remove whitespace around operators and punctuation
        $js = preg_replace('/\s*([=+\-*\/<>!&|,;{}\(\)\[\]])\s*/', '$1', $js);
        
        // Trim
        return trim($js);
    }

    // =============================================================================
    // IMPORT PROCESSING
    // =============================================================================

    /**
     * Process @import statements
     * 
     * @param string $content File content
     * @param string $type File type (scss or js)
     * @return string Content with imports resolved
     */
    private function processImports(string $content, string $type): string
    {
        $pattern = $type === 'scss' 
            ? '/@import\s+[\'"]([^\'"]+)[\'"];/'
            : '/import\s+.*?from\s+[\'"]([^\'"]+)[\'"];/';
        
        return preg_replace_callback($pattern, function ($match) use ($type) {
            $importPath = $match[1];
            
            // Skip external imports
            if (strpos($importPath, 'http') === 0 || strpos($importPath, '//') === 0) {
                return $match[0];
            }
            
            // Resolve import path
            $basePath = $type === 'scss' 
                ? $this->assetsPath . '/scss/'
                : $this->assetsPath . '/js/';
            
            $fullPath = $basePath . $importPath;
            
            // Add extension if not present
            if (!file_exists($fullPath)) {
                $fullPath .= $type === 'scss' ? '.scss' : '.js';
            }
            
            if (file_exists($fullPath)) {
                return file_get_contents($fullPath);
            }
            
            return $match[0];
        }, $content);
    }

    // =============================================================================
    // CACHE METHODS
    // =============================================================================

    /**
     * Check if cache is valid
     * 
     * @param array $sourceFiles Source files
     * @param string $outputName Output filename
     * @param string $type File type
     * @return bool True if cache is valid
     */
    private function isCacheValid(array $sourceFiles, string $outputName, string $type = 'css'): bool
    {
        $cacheFile = $this->cachePath . '/' . $outputName;
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $cacheMtime = filemtime($cacheFile);
        
        // Check if any source file is newer than cache
        foreach ($sourceFiles as $file) {
            $filePath = $type === 'css' || $type === 'scss'
                ? $this->assetsPath . '/scss/' . $file
                : $this->assetsPath . '/js/' . $file;
            
            if (file_exists($filePath) && filemtime($filePath) > $cacheMtime) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Save content to cache
     * 
     * @param string $filename Output filename
     * @param string $content Content to save
     * @return void
     */
    private function saveToCache(string $filename, string $content): void
    {
        $cacheFile = $this->cachePath . '/' . $filename;
        file_put_contents($cacheFile, $content);
    }

    /**
     * Get cache file URL
     * 
     * @param string $filename Cache filename
     * @return string URL to cached file
     */
    private function getCacheUrl(string $filename): string
    {
        global $config;
        
        $baseUrl = $config['app']['base_url'] ?? '';
        $relativeCachePath = str_replace($this->publicPath, '', $this->cachePath);
        
        return rtrim($baseUrl, '/') . $relativeCachePath . '/' . $filename;
    }

    /**
     * Clear asset cache
     * 
     * @return void
     */
    public function clearCache(): void
    {
        if (is_dir($this->cachePath)) {
            $files = glob($this->cachePath . '/*.{css,js}', GLOB_BRACE);
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    // =============================================================================
    // CONFIGURATION METHODS
    // =============================================================================

    /**
     * Set SCSS variables
     * 
     * @param array $variables Variables to inject
     * @return self For method chaining
     */
    public function setScssVariables(array $variables): self
    {
        $this->scssVariables = $variables;
        return $this;
    }

    /**
     * Enable/disable minification
     * 
     * @param bool $enabled Whether to enable minification
     * @return self For method chaining
     */
    public function setMinification(bool $enabled): self
    {
        $this->minificationEnabled = $enabled;
        return $this;
    }

    /**
     * Enable/disable caching
     * 
     * @param bool $enabled Whether to enable caching
     * @return self For method chaining
     */
    public function setCaching(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;
        return $this;
    }

    // =============================================================================
    // STATIC HELPER METHODS
    // =============================================================================

    /**
     * Get asset URL with versioning
     * 
     * @param string $path Asset path
     * @return string Asset URL with version
     */
    public static function asset(string $path): string
    {
        global $config;
        
        $baseUrl = $config['app']['base_url'] ?? '';
        $fullPath = $baseUrl . '/assets/' . ltrim($path, '/');
        
        // Add version query string for cache busting
        $filePath = ASSETS_PATH . '/' . ltrim($path, '/');
        if (file_exists($filePath)) {
            $version = filemtime($filePath);
            $fullPath .= '?v=' . $version;
        }
        
        return $fullPath;
    }

    /**
     * Get bundled CSS URL
     * 
     * @param string|array $files Files to bundle
     * @param string $outputName Output filename
     * @return string CSS URL
     */
    public static function css($files, string $outputName = 'app.css'): string
    {
        $bundler = new self();
        return $bundler->compileScss($files, $outputName);
    }

    /**
     * Get bundled JS URL
     * 
     * @param string|array $files Files to bundle
     * @param string $outputName Output filename
     * @return string JS URL
     */
    public static function js($files, string $outputName = 'app.js'): string
    {
        $bundler = new self();
        return $bundler->bundleJs($files, $outputName);
    }
}
