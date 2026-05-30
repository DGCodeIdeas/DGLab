<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\TestCase;
use DGLab\Core\View;
use DGLab\Core\Application;
use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;

class ReactiveJourneyTest extends TestCase
{
    public function test_persisted_state_journey()
    {
        // Setup routes and views
        $this->addTestRoute('POST', '/journey/step1', function () {
            $view = new View();
            file_put_contents('resources/views/step1.super.php', '~setup { $counter = 1; @persist($counter); } ~ <div>Step 1</div>');
            $output = $view->render('step1', [], null);
            @unlink('resources/views/step1.super.php');
            return $output;
        });

        $this->addTestRoute('POST', '/journey/step2', function () {
            $view = new View();
            file_put_contents('resources/views/step2.super.php', '~setup { @persist($counter); $counter++; } ~ <div>Step 2: {{ $counter }}</div>');
            $output = $view->render('step2', [], null);
            @unlink('resources/views/step2.super.php');
            return $output;
        });

        $this->addTestRoute('GET', '/journey/final', function () {
            $view = new View();
            file_put_contents('resources/views/final.super.php', '~setup { @persist($counter); } ~ <div>Final: {{ $counter }}</div>');
            $output = $view->render('final', [], null);
            @unlink('resources/views/final.super.php');
            return $output;
        });

        // Step 1: Initialize counter
        $this->post('/journey/step1');
        $this->assertPersistedStateHas('counter', 1);

        // Step 2: Increment counter
        $this->post('/journey/step2');
        $this->assertPersistedStateHas('counter', 2);
        $this->assertSee('Step 2: 2');

        // Final: Check value
        $this->get('/journey/final');
        $this->assertSee('Final: 2');
    }

    public function test_global_state_sharing()
    {
        $this->addTestRoute('GET', '/global/set', function () {
            $g = Application::getInstance()->get(GlobalStateStoreInterface::class);
            $g->set('theme', 'dark');
            return 'OK';
        });

        $this->addTestRoute('GET', '/global/check', function () {
            $view = new View();
            file_put_contents('resources/views/check_global.super.php', '~setup { @global("theme"); } ~ <div @click="alert(1)">Theme is {{ $theme }}</div>');
            $output = $view->render('check_global', [], null);
            @unlink('resources/views/check_global.super.php');
            return $output;
        });

        $this->get('/global/set');
        $response = $this->get('/global/check');

        $this->assertSee('Theme is dark');

        $content = $response->getContent();
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//*[@s-data]");

        // Debug s-data content
        if ($nodes->length > 0) {
            $sData = $nodes->item(0)->getAttribute('s-data');
            $state = json_decode(base64_decode($sData), true);
            // var_dump($state);
        }

        $this->assertGlobalStateInjected('theme', 'dark');
    }

    public function test_component_rendering_assertions()
    {
        $this->addTestRoute('GET', '/test/component-assertion', function () {
            $view = new View();
            // Just echo it directly as if it was rendered
            return '<div s-component="test_comp" s-props="eyJpZCI6MTIzfQ==">Component Content</div>';
        });

        $this->get('/test/component-assertion');
        $this->assertComponentRendered('test_comp', ['id' => 123]);
    }
}
