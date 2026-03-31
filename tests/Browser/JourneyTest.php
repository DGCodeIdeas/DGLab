<?php

namespace DGLab\Tests\Browser;

use DGLab\Tests\BrowserTestCase;
use DGLab\Tests\PageObjects\LoginPage;
use DGLab\Tests\PageObjects\DashboardPage;
use DGLab\Tests\PageObjects\NavigationComponent;
use DGLab\Models\User;

/**
 * @group browser
 */
class JourneyTest extends BrowserTestCase
{
    public function testCanonicalFlow()
    {
        // 1. Setup - Create a user in the test database
        User::create([
            'uuid' => uuid(),
            'email' => 'journey@test.com',
            'username' => 'journeyuser',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'status' => 'active'
        ]);

        $client = static::createPantherClient(['browser' => 'chrome']);
        $loginPage = new LoginPage($client);
        $dashboard = new DashboardPage($client);
        $nav = new NavigationComponent($client);

        // 2. Login
        $loginPage->login('journey@test.com', 'password123');
        $client->waitFor('.navbar');
        $this->assertTrue($dashboard->isVisible());

        // 3. Navigate to Services
        $nav->clickLink('Services');
        $this->assertStringContainsString('/services', $client->getCurrentURL());

        // 4. Perform Action - Click into a Service
        $nav->clickLink('EPUB Font Changer');
        $this->assertStringContainsString('/services/epub-font-changer', $client->getCurrentURL());

        // 5. Logout
        $dashboard->logout();
        $this->assertStringContainsString('/login', $client->getCurrentURL());
    }

    public function testGuestJourney()
    {
        $client = static::createPantherClient(['browser' => 'chrome']);
        $dashboard = new DashboardPage($client);
        $nav = new NavigationComponent($client);

        // 1. Visit Homepage
        $dashboard->open();
        $this->assertTrue($dashboard->isVisible());

        // 2. Navigate to Services (Guest Access)
        $nav->clickLink('Get Started');
        $this->assertStringContainsString('/services', $client->getCurrentURL());

        // 3. View Service Detail
        $nav->clickLink('EPUB Font Changer');
        $this->assertStringContainsString('/services/epub-font-changer', $client->getCurrentURL());
    }
}
