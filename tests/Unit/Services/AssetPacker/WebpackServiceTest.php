<?php

namespace DGLab\Tests\Unit\Services\AssetPacker;

use DGLab\Services\AssetPacker\WebpackService;
use DGLab\Services\AssetPacker\DependencyResolverInterface;
use DGLab\Core\Application;
use PHPUnit\Framework\TestCase;

class WebpackServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        // Use a temp directory and ensure it has a trailing separator removed for consistency
        $this->tempDir = rtrim(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'webpack_test_' . uniqid(), DIRECTORY_SEPARATOR);
        mkdir($this->tempDir . '/resources/js', 0777, true);
        Application::getInstance($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
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

        $dep1 = $this->tempDir . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'jquery.js';
        $dep2 = $this->tempDir . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'app.js';

        $resolver->expects($this->once())
            ->method('resolve')
            ->willReturn([$dep1, $dep2]);

        $service = new WebpackService($resolver);

        touch($this->tempDir . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'app.js');

        $result = $service->process(['entry' => 'app']);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['dependencies']);

        // Use DIRECTORY_SEPARATOR for expected paths
        $expectedDep2 = 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'app.js';
        $this->assertEquals($expectedDep2, $result['dependencies'][1]);
    }
}
