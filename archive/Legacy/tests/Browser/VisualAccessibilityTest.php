<?php

namespace DGLab\Tests\Browser;

use DGLab\Tests\BrowserTestCase;
use DGLab\Tests\Concerns\MakesVisualAssertions;
use DGLab\Tests\Concerns\MakesAccessibilityAssertions;
use Symfony\Component\Panther\Client;

/**
 * @group browser
 * @group visual
 * @group accessibility
 */
class VisualAccessibilityTest extends BrowserTestCase
{
    use MakesVisualAssertions;
    use MakesAccessibilityAssertions;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createPantherClient(['browser' => 'chrome']);
    }

    public function testHomePageVisualAndAccessibility()
    {
        $this->client->request('GET', '/');

        // Desktop
        $this->client->manage()->window()->setSize(new \Facebook\WebDriver\WebDriverDimension(1920, 1080));
        $this->assertVisualMatch('home_desktop');
        $this->assertPageIsAccessible();

        // Mobile
        $this->client->manage()->window()->setSize(new \Facebook\WebDriver\WebDriverDimension(375, 667));
        $this->assertVisualMatch('home_mobile');
    }

    public function testLoginPageVisualAndAccessibility()
    {
        $this->client->request('GET', '/login');

        $this->client->manage()->window()->setSize(new \Facebook\WebDriver\WebDriverDimension(1920, 1080));
        $this->assertVisualMatch('login_desktop');
        $this->assertPageIsAccessible();
    }

    protected function getClient(): Client
    {
        return $this->client;
    }
}
