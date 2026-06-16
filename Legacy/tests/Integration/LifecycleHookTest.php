<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\TestCase;
use DGLab\Core\View;

class LifecycleHookTest extends TestCase
{
    public function test_server_side_mount_hook_is_triggered()
    {
        $hookTriggered = false;

        $this->addTestRoute('GET', '/test/lifecycle', function () use (&$hookTriggered) {
            $view = new View();
            $view->on('mount', function () use (&$hookTriggered) {
                $hookTriggered = true;
            });

            // Create a temporary view file
            file_put_contents('resources/views/test_lifecycle.super.php', '<div>Test Lifecycle</div>');

            $output = $view->render('test_lifecycle', [], null);
            @unlink('resources/views/test_lifecycle.super.php');

            return $output;
        });

        $this->get('/test/lifecycle');
        $this->assertTrue($hookTriggered, 'Server-side mount hook was not triggered during render.');
    }

    public function test_updated_hook_logic_simulation()
    {
        $this->addTestRoute('POST', '/test/update-hook', function () {
            $view = new View();
            $updatedCount = 0;
            $view->on('updated', function () use (&$updatedCount) {
                $updatedCount++;
            });

            // Simulate an action that would trigger 'updated'
            file_put_contents('resources/views/test_updated.super.php', '<div>Updated</div>');
            $view->render('test_updated', [], null);
            $view->trigger('updated');

            @unlink('resources/views/test_updated.super.php');
            return (string)$updatedCount;
        });

        $response = $this->post('/test/update-hook');
        $this->assertEquals('1', $response->getContent());
    }
}
