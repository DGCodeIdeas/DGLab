<?php

namespace DGLab\Tests\Browser;

use DGLab\Tests\BrowserTestCase;

/**
 * @group browser
 */
class MorphingTest extends BrowserTestCase
{
    public function test_dom_morphing_on_click()
    {
        $client = static::createPantherClient();
        $crawler = $client->request('GET', static::$baseUri . '/test/morph');

        // Verify initial state
        $this->assertStringContainsString('Current count: 0', $crawler->filter('#morph-target p')->text());
        $initialTime = $crawler->filter('#static-time')->text();

        // Click increment button
        $client->executeScript("document.getElementById('increment-btn').click()");

        // Wait for fragment to update
        $client->waitForText('Current count: 1');

        // Verify state change
        $this->assertStringContainsString('Current count: 1', $client->getCrawler()->filter('#morph-target p')->text());

        // Verify static area DID NOT change (Proves morphing or at least fragment update)
        $newTime = $client->getCrawler()->filter('#static-time')->text();
        $this->assertEquals($initialTime, $newTime, 'Static area was updated when it should not have been (Full page reload instead of morphing?)');

        // Click again
        $client->executeScript("document.getElementById('increment-btn').click()");
        $client->waitForText('Current count: 2');
        $this->assertStringContainsString('Current count: 2', $client->getCrawler()->filter('#morph-target p')->text());
    }
}
