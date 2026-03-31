<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\TestCase;
use DGLab\Core\View;
use DGLab\Core\Response;

class ReactiveAssertionTest extends TestCase
{
    public function test_fragment_assertions()
    {
        $this->addTestRoute('GET', '/test/fragment', function () {
            $view = new View();
            $view->setFragmentMode('target');
            $view->setSection('target', '<div id="target">Fragment Content</div>');
            $content = $view->render('home', [], null);
            return new Response($content, 200, ['X-Superpowers-Fragment' => 'target']);
        });

        $this->get('/test/fragment');

        $this->assertResponseIsFragment('target');
        $this->assertFragmentContains('target', 'Fragment Content');
    }

    public function test_persisted_state_assertion()
    {
        $this->addTestRoute('GET', '/test/persist-assertion', function () {
            $view = new View();
            file_put_contents('resources/views/test_pa.super.php', '~setup { $val = 123; @persist($val); } ~');
            $view->render('test_pa', [], null);
            @unlink('resources/views/test_pa.super.php');
            return 'done';
        });

        $this->get('/test/persist-assertion');
        $this->assertPersistedStateHas('val', 123);
    }

    public function test_global_state_injected_assertion()
    {
        $this->addTestRoute('GET', '/test/injected-assertion', function () {
            $view = new View();
            // Directly use s-data in a div to test the assertion logic
            return '<div s-data="eyJ1c2VyIjoiSnVsZXMifQ=="> <div id="comp">Hello Jules</div> </div>';
        });

        $this->get('/test/injected-assertion');
        $this->assertGlobalStateInjected('user', 'Jules');
    }
}
