<?php

namespace DGLab\Services\AssetPacker;

use DGLab\Core\Application;

class ImportMapGenerator
{
    /**
     * Renders the HTML <script type="importmap"> block.
     */
    public function render(): string
    {
        $map = $this->generateMap();

        $json = json_encode([
            'imports' => $map
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return "<script type=\"importmap\">\n" . $json . "\n</script>";
    }

    /**
     * Generates the import map array.
     */
    public function generateMap(): array
    {
        $vendorMapPath = Application::getInstance()->getBasePath() . '/config/vendor_map.php';

        if (!file_exists($vendorMapPath)) {
            return [];
        }

        $map = include $vendorMapPath;

        // Ensure paths are absolute URLs relative to host root
        foreach ($map as $key => $path) {
            if (!str_starts_with($path, '/')) {
                $map[$key] = '/' . $path;
            }
        }

        return $map;
    }
}
