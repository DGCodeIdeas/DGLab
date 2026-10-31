<?php

declare(strict_types=1);

namespace SovereignStack\Core\View\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\View\TwigDriver;
use SovereignStack\Core\View\View;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

final class TwigDriverTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/view-tests-' . uniqid();
        mkdir($this->tmp, 0777, true);
        file_put_contents($this->tmp . '/hello.twig', 'Hello {{ name }}!');
        file_put_contents($this->tmp . '/escaped.twig', '{{ value }}');
        file_put_contents($this->tmp . '/layout.twig', '{% block content %}default{% endblock %}');
        file_put_contents($this->tmp . '/child.twig', '{% extends "layout.twig" %}{% block content %}child {{ x }}{% endblock %}');
        file_put_contents($this->tmp . '/include.twig', '{% include "partial.twig" %}');
        file_put_contents($this->tmp . '/partial.twig', 'PARTIAL {{ y }}');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->tmp);
    }

    public function testRendersVariables(): void
    {
        $driver = new TwigDriver($this->tmp);
        $this->assertSame('Hello Ada!', $driver->render('hello.twig', ['name' => 'Ada']));
    }

    public function testRepeatedRendersNoContextLeak(): void
    {
        $driver = new TwigDriver($this->tmp);
        $this->assertSame('Hello Ada!', $driver->render('hello.twig', ['name' => 'Ada']));
        $this->assertSame('Hello Bob!', $driver->render('hello.twig', ['name' => 'Bob']));
    }

    public function testAutoEscapesHtml(): void
    {
        $driver = new TwigDriver($this->tmp);
        $this->assertSame('&lt;script&gt;', $driver->render('escaped.twig', ['value' => '<script>']));
    }

    public function testTemplateInheritance(): void
    {
        $driver = new TwigDriver($this->tmp);
        $this->assertSame('child 42', $driver->render('child.twig', ['x' => 42]));
    }

    public function testIncludes(): void
    {
        $driver = new TwigDriver($this->tmp);
        $this->assertSame('PARTIAL 7', $driver->render('include.twig', ['y' => 7]));
    }

    public function testMissingTemplateThrowsLoaderError(): void
    {
        $driver = new TwigDriver($this->tmp);
        $this->expectException(LoaderError::class);
        $driver->render('missing.twig');
    }

    public function testSyntaxErrorThrowsSyntaxError(): void
    {
        file_put_contents($this->tmp . '/bad.twig', '{{ unclosed');
        $driver = new TwigDriver($this->tmp);
        $this->expectException(SyntaxError::class);
        $driver->render('bad.twig');
    }

    public function testViewDelegatesToDriver(): void
    {
        $view = new View(new TwigDriver($this->tmp));
        $this->assertSame('Hello Ada!', $view->render('hello.twig', ['name' => 'Ada']));
    }
}
