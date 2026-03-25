<?php

namespace DGLab\Tests\Unit\Services\AssetPacker;

use DGLab\Services\AssetPacker\WebpackService;
use DGLab\Services\AssetPacker\DependencyResolverInterface;
use DGLab\Core\Application;
use PHPUnit\Framework\TestCase;

class WebpackServiceTest extends \DGLab\Tests\TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = rtrim(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'webpack_test_' . uniqid(), DIRECTORY_SEPARATOR);
        mkdir($this->tempDir . '/resources/js', 0777, true);
        mkdir($this->tempDir . '/config', 0777, true);

        file_put_contents($this->tempDir . '/config/assets.php', '<?php return [
            "webpack" => [
                "entries" => ["app" => "resources/js/app.js"],
                "optimization" => ["minify" => false]
            ]
        ];');

        new Application($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        Application::flush();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testGetMetadata(): void
    {
        $service = new WebpackService();
        $this->assertEquals('webpack', $service->getId());
        $this->assertEquals('PHP Asset Bundler', $service->getName());
        $this->assertNotEmpty($service->getDescription());
    }

    public function testProcessResolvesDependencies(): void
    {
        $resolver = $this->createMock(DependencyResolverInterface::class);

        $jsPath = $this->tempDir . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js';
        $dep1 = $jsPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'jquery.js';
        $dep2 = $jsPath . DIRECTORY_SEPARATOR . 'app.js';

        $resolver->expects($this->once())
            ->method('resolve')
            ->willReturn([$dep1, $dep2]);

        $service = new WebpackService($resolver);

        mkdir($jsPath . DIRECTORY_SEPARATOR . 'vendor', 0777, true);
        touch($dep1);
        touch($dep2);

        $result = $service->process(['entry' => 'app']);

        $this->assertTrue($result['success']);
        $this->assertEquals('app', $result['entry']);
    }
}
