<?php

declare(strict_types=1);

namespace SovereignStack\Core\View\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Config\ConfigRepository;
use SovereignStack\Core\View\NeoSPXDriver;
use SovereignStack\Core\View\TwigDriver;
use SovereignStack\Core\View\ViewFactory;

final class ViewFactoryTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/view-factory-' . uniqid();
        mkdir($this->tmp, 0777, true);
        file_put_contents($this->tmp . '/t.twig', 'hi');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->tmp);
    }

    public function testTwigFromConfig(): void
    {
        $config = new ConfigRepository(['view' => ['driver' => 'twig']]);
        $view = ViewFactory::fromConfig($config, $this->tmp);
        $this->assertInstanceOf(TwigDriver::class, $view->getDriver());
        $this->assertSame('hi', $view->render('t.twig'));
    }

    public function testFromConfigUsesViewPathFromConfig(): void
    {
        $config = new ConfigRepository([
            'view' => [
                'driver' => 'twig',
                'path' => $this->tmp,
            ],
        ]);
        $view = ViewFactory::fromConfig($config);
        $this->assertInstanceOf(TwigDriver::class, $view->getDriver());
        $this->assertSame('hi', $view->render('t.twig'));
    }

    public function testFromConfigDefaultsToCwdAppResourcesViews(): void
    {
        // Create a temporary directory structure to simulate cwd/app/Resources/views
        $cwd = sys_get_temp_dir() . '/view-cwd-' . uniqid();
        $viewsDir = $cwd . '/app/Resources/views';
        mkdir($viewsDir, 0777, true);
        file_put_contents($viewsDir . '/default.twig', 'default content');

        $originalCwd = getcwd();
        chdir($cwd);

        try {
            $config = new ConfigRepository(['view' => ['driver' => 'twig']]);
            $view = ViewFactory::fromConfig($config);
            $this->assertSame('default content', $view->render('default.twig'));
        } finally {
            chdir($originalCwd);
            // Cleanup
            foreach (glob($cwd . '/*') as $f) @unlink($f);
            @rmdir($cwd . '/app/Resources/views');
            @rmdir($cwd . '/app/Resources');
            @rmdir($cwd . '/app');
            @rmdir($cwd);
        }
    }

    public function testMissingDriverThrows(): void
    {
        $config = new ConfigRepository([]);
        $this->expectException(\InvalidArgumentException::class);
        ViewFactory::fromConfig($config, $this->tmp);
    }

    public function testUnknownDriverThrows(): void
    {
        $config = new ConfigRepository(['view' => ['driver' => 'nope']]);
        $this->expectException(\InvalidArgumentException::class);
        ViewFactory::fromConfig($config, $this->tmp);
    }

    public function testNeoSPXDriverThrowsOnCreation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('NeoSPX driver is not available');
        ViewFactory::createDriver('neospx', $this->tmp);
    }

    public function testCreateDriverUnknownThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ViewFactory::createDriver('nope', $this->tmp);
    }

    public function testInvalidViewPathThrows(): void
    {
        $config = new ConfigRepository([
            'view' => [
                'driver' => 'twig',
                'path' => '/nonexistent/path',
            ],
        ]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('View template path does not exist');
        ViewFactory::fromConfig($config);
    }

    public function testExplicitTemplatePathOverridesConfig(): void
    {
        $config = new ConfigRepository([
            'view' => [
                'driver' => 'twig',
                'path' => '/should/not/be/used',
            ],
        ]);
        $view = ViewFactory::fromConfig($config, $this->tmp);
        $this->assertSame('hi', $view->render('t.twig'));
    }
}
