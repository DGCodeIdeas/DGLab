<?php

namespace DGLab\Tests\Browser;

use DGLab\Tests\BrowserTestCase;

class HomeTest extends BrowserTestCase
{
    public function testHomePageLoads()
    {
        $client = static::createPantherClient(['browser' => 'chrome']);
        $crawler = $client->request('GET', '/');

        $this->assertPageTitleContains('DGLab');
        $this->assertSelectorTextContains('h1', 'Digital Lab Tools');
    }

    public function testSPALinkNavigation()
    {
        $client = static::createPantherClient(['browser' => 'chrome']);
        $client->request('GET', '/');

        // Ensure we are on home
        $this->assertSelectorTextContains('h1', 'Digital Lab Tools');

        // Click "Get Started" which has @prefetch (data-prefetch)
        $client->clickLink('Get Started');

        // Wait for fragment transition
        $client->waitFor('h2');

        // Verify URL changed but page didn't full reload (optional, check data-fragment-loaded)
        $this->assertStringContainsString('/services', $client->getCurrentURL());
    }
}
