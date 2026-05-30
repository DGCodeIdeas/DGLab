<?php

namespace DGLab\Tests;

use DGLab\Core\View;

abstract class ComponentTestCase extends TestCase
{
    protected function renderComponent(string $name, array $props = []): string
    {
        $view = new View();
        return $view->render($name, $props, null);
    }

    protected function assertComponentRenders(string $name, array $props = [], string $expectedText = ''): void
    {
        $output = $this->renderComponent($name, $props);
        if ($expectedText) {
            $this->assertStringContainsString($expectedText, $output);
        } else {
            $this->assertNotEmpty($output);
        }
    }
}
