<?php

namespace DGLab\Tests\Unit\Services\AssetPacker;

use DGLab\Services\AssetPacker\DependencyResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DependencyResolverTest extends TestCase
{
    private string $tempDir;
    private DependencyResolver $resolver;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/dglab_tests_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->resolver = new DependencyResolver($this->tempDir);
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

    public function testResolveBasicDependencies(): void
    {
        file_put_contents($this->tempDir . '/app.js', "import './utils';\nimport 'vendor/jquery';");
        file_put_contents($this->tempDir . '/utils.js', "console.log('utils');");
        mkdir($this->tempDir . '/vendor');
        file_put_contents($this->tempDir . '/vendor/jquery.js', "console.log('jquery');");

        $resolved = $this->resolver->resolve($this->tempDir . '/app.js');

        $this->assertCount(3, $resolved);
        $this->assertEquals($this->tempDir . DIRECTORY_SEPARATOR . 'utils.js', $resolved[0]);
        $this->assertEquals($this->tempDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'jquery.js', $resolved[1]);
        $this->assertEquals($this->tempDir . DIRECTORY_SEPARATOR . 'app.js', $resolved[2]);
    }

    public function testRecursiveDependencies(): void
    {
        file_put_contents($this->tempDir . '/a.js', "import './b';");
        file_put_contents($this->tempDir . '/b.js', "import './c';");
        file_put_contents($this->tempDir . '/c.js', "console.log('c');");

        $resolved = $this->resolver->resolve($this->tempDir . '/a.js');

        $this->assertCount(3, $resolved);
        $this->assertEquals($this->tempDir . DIRECTORY_SEPARATOR . 'c.js', $resolved[0]);
        $this->assertEquals($this->tempDir . DIRECTORY_SEPARATOR . 'b.js', $resolved[1]);
        $this->assertEquals($this->tempDir . DIRECTORY_SEPARATOR . 'a.js', $resolved[2]);
    }

    public function testCircularDependencyThrowsException(): void
    {
        file_put_contents($this->tempDir . '/a.js', "import './b';");
        file_put_contents($this->tempDir . '/b.js', "import './a';");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');
        $this->resolver->resolve($this->tempDir . '/a.js');
    }

    public function testMissingFileThrowsException(): void
    {
        file_put_contents($this->tempDir . '/a.js', "import './non-existent';");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found');
        $this->resolver->resolve($this->tempDir . '/a.js');
    }

    public function testIgnoresDependenciesInComments(): void
    {
        file_put_contents($this->tempDir . '/app.js', "
            // import './ignored1';
            /*
               import './ignored2';
               require('./ignored3');
            */
            import './real';
        ");
        file_put_contents($this->tempDir . '/real.js', "console.log('real');");

        $resolved = $this->resolver->resolve($this->tempDir . '/app.js');

        $this->assertCount(2, $resolved);
        $this->assertEquals($this->tempDir . DIRECTORY_SEPARATOR . 'real.js', $resolved[0]);
        $this->assertEquals($this->tempDir . DIRECTORY_SEPARATOR . 'app.js', $resolved[1]);
    }

    public function testIgnoresDependenciesInStrings(): void
    {
        file_put_contents($this->tempDir . '/app.js', "
            const a = \"import './ignored_double';\";
            const b = 'import \"./ignored_single\";';
            const c = `import './ignored_backtick';`;
            import './real';
        ");
        file_put_contents($this->tempDir . '/real.js', "console.log('real');");

        $resolved = $this->resolver->resolve($this->tempDir . '/app.js');

        $this->assertCount(2, $resolved);
        $this->assertEquals($this->tempDir . DIRECTORY_SEPARATOR . 'real.js', $resolved[0]);
        $this->assertEquals($this->tempDir . DIRECTORY_SEPARATOR . 'app.js', $resolved[1]);
    }

    public function testSupportedSyntax(): void
    {
        file_put_contents($this->tempDir . '/app.js', "
            import { something } from './import-from';
            import './import-only';
            const dep = require('./require');
            const asyncDep = import('./dynamic-import');
        ");
        file_put_contents($this->tempDir . '/import-from.js', "");
        file_put_contents($this->tempDir . '/import-only.js', "");
        file_put_contents($this->tempDir . '/require.js', "");
        file_put_contents($this->tempDir . '/dynamic-import.js', "");

        $resolved = $this->resolver->resolve($this->tempDir . '/app.js');

        $this->assertCount(5, $resolved);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'import-from.js', $resolved);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'import-only.js', $resolved);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'require.js', $resolved);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'dynamic-import.js', $resolved);
        $this->assertEquals($this->tempDir . DIRECTORY_SEPARATOR . 'app.js', end($resolved));
    }
}
