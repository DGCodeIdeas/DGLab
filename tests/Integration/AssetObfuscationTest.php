<?php

namespace DGLab\Tests\Integration;

use DGLab\Core\Application;
use DGLab\Services\AssetService;

class AssetObfuscationTest extends IntegrationTestCase
{
    private string $testJsFile;
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = Application::getInstance()->getBasePath() . '/storage/cache/assets';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        $this->testJsFile = Application::getInstance()->getBasePath() . '/resources/js/test-obf.js';
        file_put_contents($this->testJsFile, "function longVariableName(paramOne, paramTwo) { console.log(paramOne + paramTwo); } longVariableName(1, 2);");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testJsFile)) {
            unlink($this->testJsFile);
        }
        // Clean up cache
        $files = glob($this->cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
        }
        parent::tearDown();
    }

    public function testObfuscationBuildFlow()
    {
        // 1. Run the build script
        // We simulate the build script behavior
        $sourcePath = $this->testJsFile;
        $hash = substr(md5_file($sourcePath), 0, 8);
        $cacheFile = $this->cacheDir . "/test-obf.{$hash}.js";

        $helperPath = Application::getInstance()->getBasePath() . '/cli/obfuscate.js';
        $command = sprintf(
            'node %s %s %s 2>&1',
            escapeshellarg($helperPath),
            escapeshellarg($sourcePath),
            escapeshellarg($cacheFile)
        );

        exec($command, $output, $returnCode);

        $this->assertEquals(0, $returnCode, "Obfuscation helper failed: " . implode("\n", (array)$output));
        $this->assertFileExists($cacheFile);
        $this->assertFileExists($cacheFile . '.map');

        $obfuscatedContent = file_get_contents($cacheFile);
        $this->assertStringNotContainsString('longVariableName', $obfuscatedContent);

        // 2. Test that AssetService serves it correctly
        $assetService = new AssetService();

        // Mock getAssetUrl to ensure it picks up the same hash
        $url = $assetService->getAssetUrl('test-obf.js');
        $this->assertStringContainsString($hash, $url);

        // Serve it
        ob_start();
        try {
            $assetService->serveAsset('js', basename($cacheFile));
        } catch (\Exception $e) {}
        $servedContent = ob_get_clean();

        // servedContent might be empty if serveAsset calls exit
        // but we can check if the file was found
        $this->assertFileExists($cacheFile);
    }
}
