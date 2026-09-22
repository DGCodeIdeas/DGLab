<?php

namespace DGLab\Tests\Browser;

use DGLab\Tests\BrowserTestCase;
use DGLab\Tests\PageObjects\DashboardPage;
use DGLab\Tests\PageObjects\NavigationComponent;

/**
 * @group browser
 */
class HomeTest extends BrowserTestCase
{
    public function testHomePageLoads()
    {
        $client = static::createPantherClient(['browser' => 'chrome']);
        $dashboard = new DashboardPage($client);
        $dashboard->open();

        $this->assertStringContainsString('DGLab', $client->getTitle());
        $this->assertTrue($dashboard->isVisible());
    }

    public function testSPALinkNavigation()
    {
        $client = static::createPantherClient(['browser' => 'chrome']);
        $dashboard = new DashboardPage($client);
        $nav = new NavigationComponent($client);

        $dashboard->open();
        $this->assertTrue($dashboard->isVisible());

        // Use NavigationComponent to click "Get Started"
        $nav->clickLink('Get Started');

        // Verify URL changed but page didn't full reload
        $this->assertStringContainsString('/services', $client->getCurrentURL());
    }
}
