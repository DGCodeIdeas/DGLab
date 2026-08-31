<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\TestCase;
use DGLab\Core\View;
use DGLab\Core\Response;

class LifecycleOrderTest extends TestCase
{
    public function test_lifecycle_hook_order()
    {
        $order = [];

        $this->addTestRoute('GET', '/test/lifecycle-order', function () use (&$order) {
            $view = new View();

            $view->on('mount', function () use (&$order) {
                $order[] = 'mount';
            });

            $view->on('cleanup', function () use (&$order) {
                $order[] = 'cleanup';
            });

            // We can also trigger custom hooks from within the template if we wanted to,
            // but here we just want to see the core ones.

            file_put_contents('resources/views/test_order.super.php', '<div>~setup { $this->trigger("custom"); } ~ Order Test</div>');
            $view->on('custom', function() use (&$order) {
                $order[] = 'custom';
            });

            $output = $view->render('test_order', [], null);
            @unlink('resources/views/test_order.super.php');

            return $output;
        });

        $this->get('/test/lifecycle-order');

        $this->assertEquals(['custom', 'mount', 'cleanup'], $order, 'Lifecycle hooks did not fire in the expected order.');
    }
}
