<?php

namespace DGLab\Tests\Browser;

use DGLab\Tests\BrowserTestCase;
use DGLab\Tests\PageObjects\DashboardPage;
use DGLab\Tests\PageObjects\NavigationComponent;

/**
 * @group browser
 */
class HistoryTest extends BrowserTestCase
{
    public function testHistoryBackAndForward()
    {
        $client = static::createPantherClient(['browser' => 'chrome']);
        $dashboard = new DashboardPage($client);
        $nav = new NavigationComponent($client);

        // 1. Start at Home
        $dashboard->open();
        $this->assertTrue($dashboard->isVisible());
        $initialUrl = $client->getCurrentURL();

        // 2. Navigate to Services
        $nav->clickLink('Get Started');
        $client->waitForInvisibility('.sp-transition-loading');
        $servicesUrl = $client->getCurrentURL();
        $this->assertStringContainsString('/services', $servicesUrl);
        $this->assertNotEquals($initialUrl, $servicesUrl);

        // 3. Navigate to a specific service
        $nav->clickLink('EPUB Font Changer');
        $client->waitForInvisibility('.sp-transition-loading');
        $detailUrl = $client->getCurrentURL();
        $this->assertStringContainsString('/services/epub-font-changer', $detailUrl);

        // 4. Go Back
        $client->back();
        $client->waitForInvisibility('.sp-transition-loading');
        $this->assertEquals($servicesUrl, $client->getCurrentURL());

        // 5. Go Back again
        $client->back();
        $client->waitForInvisibility('.sp-transition-loading');
        $this->assertEquals($initialUrl, $client->getCurrentURL());

        // 6. Go Forward
        $client->forward();
        $client->waitForInvisibility('.sp-transition-loading');
        $this->assertEquals($servicesUrl, $client->getCurrentURL());
    }
}
